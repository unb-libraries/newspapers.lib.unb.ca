//! Publishes run notifications to Amazon SNS: the full summary to one topic, a one-line event to
//! another, as the PHP importer did.
//!
//! Credentials come from the standard AWS chain (environment, `~/.aws/credentials`, profiles),
//! never from this tool's config. TLS uses `ring`, like the site client.

use aws_config::{BehaviorVersion, Region};
use aws_sdk_sns::Client;
use aws_smithy_http_client::Builder;
use aws_smithy_http_client::tls::{Provider, rustls_provider::CryptoMode};

use crate::config::Notify;

#[derive(Debug, thiserror::Error)]
#[error("publishing to {topic} failed: {message}")]
pub struct NotifyError {
    topic: String,
    message: String,
}

#[derive(Debug, Clone)]
pub struct Notifier {
    client: Client,
    summary_topic: String,
    event_topic: String,
}

impl Notifier {
    pub async fn new(config: &Notify) -> Self {
        let http = Builder::new()
            .tls_provider(Provider::Rustls(CryptoMode::Ring))
            .build_https();
        let sdk = aws_config::defaults(BehaviorVersion::latest())
            .region(Region::new(config.region.clone()))
            .http_client(http)
            .load()
            .await;
        Self {
            client: Client::new(&sdk),
            summary_topic: config.summary_topic_arn.clone(),
            event_topic: config.event_topic_arn.clone(),
        }
    }

    /// Sends the run summary and the one-line event.
    pub async fn send(&self, summary: &str, event: &str) -> Result<(), NotifyError> {
        self.publish(&self.summary_topic, summary).await?;
        self.publish(&self.event_topic, event).await
    }

    async fn publish(&self, topic: &str, message: &str) -> Result<(), NotifyError> {
        self.client
            .publish()
            .topic_arn(topic)
            .message(message)
            .send()
            .await
            .map(|_| ())
            .map_err(|error| NotifyError {
                topic: topic.to_owned(),
                // The plain Display says only "service error"; this includes the reason.
                message: format!("{}", aws_sdk_sns::error::DisplayErrorContext(&error)),
            })
    }
}
