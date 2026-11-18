//! An HTTP client for the site's ingest endpoints: basic auth, time limits, and retries for reads.

use std::sync::Once;
use std::time::Duration;

use reqwest::StatusCode;
use reqwest::header::{CONTENT_DISPOSITION, CONTENT_TYPE};
use serde::de::DeserializeOwned;
use tracing::{debug, warn};

use super::contract::{self, ErrorBody, Issue, IssueSummary, Title};
use crate::config::{Site, SiteCredentials};

const JSON: &str = "application/json";

/// Characters of an unexpected response body kept in an error.
const BODY_EXCERPT: usize = 300;

#[derive(Debug, thiserror::Error)]
pub enum SiteError {
    #[error("cannot set up the HTTP client: {0}")]
    Setup(reqwest::Error),
    #[error("{url}: {source}")]
    Request { url: String, source: reqwest::Error },
    #[error("{url}: the site refused the credentials ({status})")]
    Unauthorized { url: String, status: StatusCode },
    #[error(
        "{url}: no such endpoint (404 without the module's JSON); is the \
         digital_serial_ingest module enabled on this site?"
    )]
    NoEndpoint { url: String },
    #[error("{url}: {status}: {body}")]
    Status {
        url: String,
        status: StatusCode,
        body: String,
    },
    #[error("{0}")]
    Unexpected(String),
    #[error("{url}: unexpected response: {source}")]
    Decode {
        url: String,
        source: serde_json::Error,
    },
}

#[derive(Debug, Clone)]
pub struct Client {
    http: reqwest::Client,
    base_url: String,
    username: String,
    password: String,
    retries: u32,
    backoff: Duration,
}

impl Client {
    pub fn new(site: &Site, credentials: SiteCredentials) -> Result<Self, SiteError> {
        install_crypto_provider();
        let http = reqwest::Client::builder()
            .connect_timeout(Duration::from_secs(site.connect_timeout_secs))
            .timeout(Duration::from_secs(site.request_timeout_secs))
            .user_agent(concat!("nbnp-ingest/", env!("CARGO_PKG_VERSION")))
            .build()
            .map_err(SiteError::Setup)?;
        Ok(Self {
            http,
            base_url: site.base_url.clone(),
            username: credentials.username,
            password: credentials.password,
            retries: site.retries,
            backoff: Duration::from_millis(site.retry_backoff_ms),
        })
    }

    pub async fn issue_by_uuid(&self, uuid: &str) -> Result<Option<Issue>, SiteError> {
        self.get(&contract::issue_by_uuid(uuid)).await
    }

    pub async fn issue_by_id(&self, id: u64) -> Result<Option<Issue>, SiteError> {
        self.get(&contract::issue_by_id(id)).await
    }

    pub async fn title(&self, id: u32) -> Result<Option<Title>, SiteError> {
        self.get(&contract::title(id)).await
    }

    pub async fn title_issues(&self, id: u32) -> Result<Option<Vec<IssueSummary>>, SiteError> {
        let list: Option<contract::TitleIssues> = self.get(&contract::title_issues(id)).await?;
        Ok(list.map(|list| list.issues))
    }

    /// Confirms the site answers and accepts the credentials, by asking for an issue that does
    /// not exist.
    pub async fn check_access(&self) -> Result<(), SiteError> {
        let _: Option<Issue> = self
            .get(&contract::issue_by_uuid(contract::PROBE_UUID))
            .await?;
        Ok(())
    }

    /// Creates an issue. Never retried: after any error, look the issue up by its UUID.
    pub async fn create_issue(&self, body: &serde_json::Value) -> Result<u64, SiteError> {
        let created: contract::Created = self
            .post_json(
                contract::ISSUE_CREATE,
                body.to_string().into_bytes(),
                JSON,
                None,
            )
            .await?;
        created.id()
    }

    /// Creates a page. Never retried: after any error, look the page up by its UUID.
    pub async fn create_page(&self, body: &serde_json::Value) -> Result<u64, SiteError> {
        let created: contract::Created = self
            .post_json(
                contract::PAGE_CREATE,
                body.to_string().into_bytes(),
                JSON,
                None,
            )
            .await?;
        created.id()
    }

    /// Uploads a page image and returns the file's ID. The file stays temporary, and is
    /// removed by the site, unless a page uses it, so a failed upload is retried.
    pub async fn upload_page_image(
        &self,
        image: Vec<u8>,
        file_name: &str,
    ) -> Result<u64, SiteError> {
        let disposition = format!("file; filename=\"{file_name}\"");
        let mut wait = self.backoff;
        let mut attempt = 0;
        loop {
            attempt += 1;
            let result: Result<contract::UploadedFile, SiteError> = self
                .post_json(
                    contract::PAGE_IMAGE_UPLOAD,
                    image.clone(),
                    "application/octet-stream",
                    Some(&disposition),
                )
                .await;
            match result {
                Err(error) if attempt <= self.retries && is_transient(&error) => {
                    warn!("{error}; retrying in {wait:?} ({attempt}/{})", self.retries);
                    tokio::time::sleep(wait).await;
                    wait = wait.saturating_mul(2);
                }
                result => return result?.fid(),
            }
        }
    }

    /// `POST`s a body and decodes the JSON answer. One attempt.
    async fn post_json<T: DeserializeOwned>(
        &self,
        path: &str,
        body: Vec<u8>,
        content_type: &str,
        disposition: Option<&str>,
    ) -> Result<T, SiteError> {
        let url = format!("{}{path}", self.base_url);
        let label = format!("POST {url}");
        debug!("{label}");
        let request_error = |source| SiteError::Request {
            url: label.clone(),
            source,
        };
        let mut request = self
            .http
            .post(&url)
            .basic_auth(&self.username, Some(&self.password))
            .header(CONTENT_TYPE, content_type)
            .body(body);
        if let Some(disposition) = disposition {
            request = request.header(CONTENT_DISPOSITION, disposition);
        }
        let response = request.send().await.map_err(request_error)?;
        let status = response.status();
        let text = response.text().await.map_err(request_error)?;
        match status {
            s if s.is_success() => {
                serde_json::from_str(&text).map_err(|source| SiteError::Decode {
                    url: label.clone(),
                    source,
                })
            }
            StatusCode::UNAUTHORIZED | StatusCode::FORBIDDEN => Err(SiteError::Unauthorized {
                url: label.clone(),
                status,
            }),
            _ => Err(SiteError::Status {
                url: label.clone(),
                status,
                body: text.chars().take(BODY_EXCERPT).collect(),
            }),
        }
    }

    /// `GET`s a JSON document. `None` when the site says the thing does not exist.
    ///
    /// Connection failures, time-outs and 5xx/429 answers are retried with doubling waits;
    /// anything else is final. Only reads use this: a retried create could create twice.
    async fn get<T: DeserializeOwned>(&self, path: &str) -> Result<Option<T>, SiteError> {
        let url = format!("{}{path}", self.base_url);
        let label = format!("GET {url}");
        let mut wait = self.backoff;
        let mut attempt = 0;
        loop {
            attempt += 1;
            match self.get_once(&url).await {
                Err(error) if attempt <= self.retries && is_transient(&error) => {
                    warn!("{error}; retrying in {wait:?} ({attempt}/{})", self.retries);
                    tokio::time::sleep(wait).await;
                    wait = wait.saturating_mul(2);
                }
                result => {
                    let body = result?;
                    return body
                        .map(|text| {
                            serde_json::from_str(&text).map_err(|source| SiteError::Decode {
                                url: label.clone(),
                                source,
                            })
                        })
                        .transpose();
                }
            }
        }
    }

    async fn get_once(&self, url: &str) -> Result<Option<String>, SiteError> {
        debug!("GET {url}");
        let label = format!("GET {url}");
        let request_error = |source| SiteError::Request {
            url: label.clone(),
            source,
        };
        let response = self
            .http
            .get(url)
            .basic_auth(&self.username, Some(&self.password))
            .send()
            .await
            .map_err(request_error)?;
        let status = response.status();
        let is_json = response
            .headers()
            .get(CONTENT_TYPE)
            .and_then(|v| v.to_str().ok())
            .is_some_and(|v| v.starts_with("application/json"));
        let text = response.text().await.map_err(request_error)?;

        match status {
            s if s.is_success() => Ok(Some(text)),
            StatusCode::NOT_FOUND if is_json => match serde_json::from_str::<ErrorBody>(&text) {
                Ok(body) => {
                    debug!("{url}: {}", body.error);
                    Ok(None)
                }
                Err(_) => Err(SiteError::NoEndpoint { url: label.clone() }),
            },
            StatusCode::NOT_FOUND => Err(SiteError::NoEndpoint { url: label.clone() }),
            StatusCode::UNAUTHORIZED | StatusCode::FORBIDDEN => Err(SiteError::Unauthorized {
                url: label.clone(),
                status,
            }),
            _ => Err(SiteError::Status {
                url: label.clone(),
                status,
                body: text.chars().take(BODY_EXCERPT).collect(),
            }),
        }
    }
}

fn is_transient(error: &SiteError) -> bool {
    match error {
        SiteError::Request { source, .. } => {
            source.is_connect() || source.is_timeout() || source.is_request()
        }
        SiteError::Status { status, .. } => {
            status.is_server_error() || *status == StatusCode::TOO_MANY_REQUESTS
        }
        _ => false,
    }
}

/// rustls needs one process-wide crypto provider. `ring` builds for static musl binaries with
/// only a C compiler, which the default (`aws-lc-rs`) does not.
fn install_crypto_provider() {
    static INSTALL: Once = Once::new();
    INSTALL.call_once(|| {
        // Fails only if a provider is already installed, which is just as good.
        let _ = rustls::crypto::ring::default_provider().install_default();
    });
}

#[cfg(test)]
mod tests {
    use super::*;
    use wiremock::matchers::{header_exists, method, path};
    use wiremock::{Mock, MockServer, ResponseTemplate};

    fn client(server: &MockServer) -> Client {
        let site: Site = toml::from_str(&format!(
            "base_url = \"{}\"\ncredentials_file = \"/x\"\nretries = 2\nretry_backoff_ms = 1",
            server.uri()
        ))
        .unwrap();
        Client::new(
            &site,
            SiteCredentials {
                username: "u".into(),
                password: "p".into(),
            },
        )
        .unwrap()
    }

    fn json(status: u16, body: &str) -> ResponseTemplate {
        ResponseTemplate::new(status).set_body_raw(body.to_owned(), "application/json")
    }

    #[tokio::test]
    async fn the_modules_404_means_absent() {
        let server = MockServer::start().await;
        Mock::given(method("GET"))
            .and(path("/serials-ingest/titles/9"))
            .and(header_exists("authorization"))
            .respond_with(json(404, r#"{"error":"No digital title 9."}"#))
            .mount(&server)
            .await;
        assert_eq!(client(&server).title(9).await.unwrap(), None);
    }

    #[tokio::test]
    async fn drupals_own_404_is_an_error() {
        let server = MockServer::start().await;
        Mock::given(method("GET"))
            .respond_with(ResponseTemplate::new(404).set_body_string("<html>Page not found</html>"))
            .mount(&server)
            .await;
        let error = client(&server).title(9).await.unwrap_err();
        assert!(matches!(error, SiteError::NoEndpoint { .. }), "{error}");
    }

    #[tokio::test]
    async fn refused_credentials_are_not_retried() {
        let server = MockServer::start().await;
        Mock::given(method("GET"))
            .respond_with(ResponseTemplate::new(403))
            .expect(1)
            .mount(&server)
            .await;
        let error = client(&server).check_access().await.unwrap_err();
        assert!(matches!(error, SiteError::Unauthorized { .. }), "{error}");
    }

    #[tokio::test]
    async fn server_errors_are_retried_then_succeed() {
        let server = MockServer::start().await;
        Mock::given(method("GET"))
            .respond_with(ResponseTemplate::new(503))
            .up_to_n_times(2)
            .mount(&server)
            .await;
        Mock::given(method("GET"))
            .respond_with(json(
                200,
                r#"{"id":48,"uuid":"u","name":"The News","label":"The News (Moncton)"}"#,
            ))
            .mount(&server)
            .await;
        let title = client(&server).title(48).await.unwrap().unwrap();
        assert_eq!(title.name.as_deref(), Some("The News"));
    }

    #[tokio::test]
    async fn retries_stop_at_the_limit() {
        let server = MockServer::start().await;
        Mock::given(method("GET"))
            .respond_with(ResponseTemplate::new(500).set_body_string("boom"))
            .expect(3)
            .mount(&server)
            .await;
        let error = client(&server).title(48).await.unwrap_err();
        assert!(
            error
                .to_string()
                .ends_with("500 Internal Server Error: boom"),
            "{error}"
        );
    }
}
