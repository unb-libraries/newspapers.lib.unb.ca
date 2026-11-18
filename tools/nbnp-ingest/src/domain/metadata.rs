//! Issue metadata: the `issue.toml` and `title.toml` files, how they combine, and the limits the
//! site puts on each value.

pub mod legacy;

use std::collections::BTreeMap;

use serde::{Deserialize, Deserializer};
use time::{Date, Month};

/// Metadata for one issue, with every value the site needs except, possibly, the title ID.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct IssueMetadata {
    /// The Digital Title on the site. Only `title.toml` or `issue.toml` provide it.
    pub title_id: Option<u32>,
    pub title: String,
    pub date: Date,
    pub volume: String,
    pub issue: String,
    pub edition: String,
    pub supplement_title: String,
    pub missing_pages: String,
    pub errata: String,
    pub language: String,
    pub media: String,
}

impl IssueMetadata {
    /// The issue title the site stores: the supplement title for a supplement.
    #[must_use]
    pub fn issue_title(&self) -> &str {
        if self.supplement_title.is_empty() {
            &self.title
        } else {
            &self.supplement_title
        }
    }

    /// Each value the site would reject or store differently, given the languages it accepts.
    #[must_use]
    pub fn problems(&self, languages: &[&str]) -> Vec<String> {
        let mut problems = Vec::new();
        for (key, value, max) in [
            ("issue title", self.issue_title(), limits::ISSUE_TITLE),
            ("volume", self.volume.as_str(), limits::VOLUME),
            ("issue", self.issue.as_str(), limits::ISSUE),
            ("edition", self.edition.as_str(), limits::EDITION),
            (
                "missing_pages",
                self.missing_pages.as_str(),
                limits::MISSING_PAGES,
            ),
            ("media", self.media.as_str(), limits::MEDIA),
        ] {
            let length = value.chars().count();
            if length > max {
                problems.push(format!(
                    "{key} is {length} characters; the site stores at most {max}"
                ));
            }
        }
        if self.title.trim().is_empty() {
            problems.push("title is empty".to_owned());
        }
        if self.media.trim().is_empty() {
            problems.push("media is empty".to_owned());
        }
        if !languages.contains(&self.language.as_str()) {
            problems.push(format!(
                "language {:?} is not one of {}",
                self.language,
                languages.join(", ")
            ));
        }
        problems
    }

    /// The names and values of the fields that differ from `other`, for reports.
    #[must_use]
    pub fn differences(&self, other: &Self) -> Vec<String> {
        let ours = self.fields();
        let theirs = other.fields();
        ours.iter()
            .zip(&theirs)
            .filter(|((_, a), (_, b))| a != b)
            .map(|((key, a), (_, b))| format!("{key}: {a:?} vs {b:?}"))
            .collect()
    }

    fn fields(&self) -> [(&'static str, String); 11] {
        [
            (
                "title_id",
                self.title_id.map_or_else(String::new, |id| id.to_string()),
            ),
            ("title", self.title.clone()),
            ("date", self.date.to_string()),
            ("volume", self.volume.clone()),
            ("issue", self.issue.clone()),
            ("edition", self.edition.clone()),
            ("supplement_title", self.supplement_title.clone()),
            ("missing_pages", self.missing_pages.clone()),
            ("errata", self.errata.clone()),
            ("language", self.language.clone()),
            ("media", self.media.clone()),
        ]
    }
}

/// The longest value, in characters, the site stores in each issue field.
pub mod limits {
    pub const ISSUE_TITLE: usize = 256;
    pub const VOLUME: usize = 32;
    pub const ISSUE: usize = 32;
    pub const EDITION: usize = 128;
    pub const MISSING_PAGES: usize = 128;
    pub const MEDIA: usize = 50;
    pub const PAGE_NO: usize = 32;
}

/// A `title.toml`: what every issue below it shares.
#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct TitleFile {
    pub title_id: u32,
    pub title: Option<String>,
    pub language: Option<String>,
    pub media: Option<String>,
}

impl TitleFile {
    pub fn parse(text: &str) -> Result<Self, toml::de::Error> {
        toml::from_str(text)
    }
}

/// The values one issue sets: an `issue.toml`, or what a legacy `metadata.php` defines.
/// Anything left out comes from the title, or is empty for the optional fields.
#[derive(Debug, Clone, Default, PartialEq, Eq, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct IssueFields {
    pub title_id: Option<u32>,
    pub title: Option<String>,
    #[serde(default, deserialize_with = "toml_date")]
    pub date: Option<Date>,
    pub volume: Option<String>,
    pub issue: Option<String>,
    pub edition: Option<String>,
    pub supplement_title: Option<String>,
    pub missing_pages: Option<String>,
    pub errata: Option<String>,
    pub language: Option<String>,
    pub media: Option<String>,
}

/// Keys an issue must have, from itself or its title.
#[derive(Debug, Clone, PartialEq, Eq, thiserror::Error)]
#[error("missing {}", .0.join(", "))]
pub struct MissingKeys(pub Vec<&'static str>);

impl IssueFields {
    pub fn parse(text: &str) -> Result<Self, toml::de::Error> {
        toml::from_str(text)
    }

    /// Fills what this issue leaves out from its title.
    pub fn resolve(self, title: Option<&TitleFile>) -> Result<IssueMetadata, MissingKeys> {
        let mut missing = Vec::new();
        let mut required = |value: Option<String>, key: &'static str| {
            value.unwrap_or_else(|| {
                missing.push(key);
                String::new()
            })
        };
        let from_title =
            |pick: fn(&TitleFile) -> &Option<String>| title.and_then(|t| pick(t).clone());

        let title_name = required(self.title.or_else(|| from_title(|t| &t.title)), "title");
        let volume = required(self.volume, "volume");
        let issue = required(self.issue, "issue");
        let language = required(
            self.language.or_else(|| from_title(|t| &t.language)),
            "language",
        );
        let media = required(self.media.or_else(|| from_title(|t| &t.media)), "media");
        if self.date.is_none() {
            missing.push("date");
        }
        let (Some(date), true) = (self.date, missing.is_empty()) else {
            return Err(MissingKeys(missing));
        };

        Ok(IssueMetadata {
            title_id: self.title_id.or(title.map(|t| t.title_id)),
            title: title_name,
            date,
            volume,
            issue,
            edition: self.edition.unwrap_or_default(),
            supplement_title: self.supplement_title.unwrap_or_default(),
            missing_pages: self.missing_pages.unwrap_or_default(),
            errata: self.errata.unwrap_or_default(),
            language,
            media,
        })
    }
}

/// Reads a TOML local date (`1909-01-29`), refusing times and offsets.
fn toml_date<'de, D: Deserializer<'de>>(deserializer: D) -> Result<Option<Date>, D::Error> {
    use serde::de::Error as _;

    let value = toml::value::Datetime::deserialize(deserializer)?;
    let (Some(date), None, None) = (value.date, value.time, value.offset) else {
        return Err(D::Error::custom(format!(
            "{value} is not a date like 1909-01-29"
        )));
    };
    let month = Month::try_from(date.month).map_err(D::Error::custom)?;
    Date::from_calendar_date(i32::from(date.year), month, date.day)
        .map(Some)
        .map_err(|error| D::Error::custom(format!("{value}: {error}")))
}

/// Renders the `issue.toml` for `metadata`: the values `title` does not already provide, so a
/// change to the title file reaches every issue that did not override it.
#[must_use]
pub fn render_issue_toml(metadata: &IssueMetadata, title: &TitleFile, notes: &[String]) -> String {
    let mut out = String::new();
    for note in notes {
        for line in note.lines() {
            out.push_str("# ");
            out.push_str(line);
            out.push('\n');
        }
    }
    let quoted = |value: &str| toml::Value::String(value.to_owned()).to_string();
    let mut keys: BTreeMap<u8, String> = BTreeMap::new();
    let mut put = |order: u8, key: &str, value: String| {
        keys.insert(order, format!("{key} = {value}\n"));
    };

    put(0, "date", metadata.date.to_string());
    put(1, "volume", quoted(&metadata.volume));
    put(2, "issue", quoted(&metadata.issue));
    for (order, key, value) in [
        (3, "edition", &metadata.edition),
        (4, "supplement_title", &metadata.supplement_title),
        (5, "missing_pages", &metadata.missing_pages),
        (6, "errata", &metadata.errata),
    ] {
        if !value.is_empty() {
            put(order, key, quoted(value));
        }
    }
    for (order, key, value, inherited) in [
        (7, "title", &metadata.title, &title.title),
        (8, "language", &metadata.language, &title.language),
        (9, "media", &metadata.media, &title.media),
    ] {
        if inherited.as_ref() != Some(value) {
            put(order, key, quoted(value));
        }
    }
    if metadata.title_id.is_some_and(|id| id != title.title_id) {
        put(
            10,
            "title_id",
            metadata.title_id.unwrap_or_default().to_string(),
        );
    }
    out.extend(keys.into_values());
    out
}

#[cfg(test)]
mod tests {
    use super::*;

    const TITLE: &str = r#"
        title_id = 48
        title = "The News"
        language = "eng"
        media = "print"
    "#;

    fn title() -> TitleFile {
        TitleFile::parse(TITLE).unwrap()
    }

    fn date(text: &str) -> Date {
        IssueFields::parse(&format!("date = {text}"))
            .unwrap()
            .date
            .unwrap()
    }

    #[test]
    fn an_issue_inherits_and_overrides_its_title() {
        let fields = IssueFields::parse(
            r#"
            date = 1909-01-29
            volume = "33"
            issue = "5"
            media = "microfilm"
            "#,
        )
        .unwrap();
        let metadata = fields.resolve(Some(&title())).unwrap();
        assert_eq!(metadata.title_id, Some(48));
        assert_eq!(metadata.title, "The News");
        assert_eq!(metadata.media, "microfilm");
        assert_eq!(metadata.date.to_string(), "1909-01-29");
        assert_eq!(metadata.edition, "");
    }

    #[test]
    fn missing_keys_are_listed_together() {
        let fields = IssueFields::parse("volume = \"1\"").unwrap();
        let error = fields.resolve(None).unwrap_err();
        assert_eq!(error.0, vec!["title", "issue", "language", "media", "date"]);
    }

    #[test]
    fn unknown_keys_are_errors() {
        assert!(IssueFields::parse("volumne = \"1\"").is_err());
        assert!(TitleFile::parse("title_id = 1\ndate = 1909-01-01").is_err());
    }

    #[test]
    fn dates_must_be_plain_real_dates() {
        assert_eq!(date("1803-07-04").to_string(), "1803-07-04");
        assert!(IssueFields::parse("date = 1909-02-30").is_err());
        assert!(IssueFields::parse("date = 1909-01-29T00:00:00").is_err());
        assert!(IssueFields::parse("date = \"1909-01-29\"").is_err());
    }

    #[test]
    fn problems_name_each_value_the_site_would_refuse() {
        let mut metadata = IssueFields::parse("date = 1909-01-29\nvolume = \"1\"\nissue = \"1\"")
            .unwrap()
            .resolve(Some(&title()))
            .unwrap();
        assert!(metadata.problems(&["eng", "fre"]).is_empty());

        metadata.volume = "x".repeat(33);
        metadata.language = "deu".into();
        let problems = metadata.problems(&["eng", "fre"]);
        assert_eq!(problems.len(), 2, "{problems:?}");
        assert!(problems[0].starts_with("volume is 33 characters"));
        assert!(problems[1].contains("\"deu\""));
    }

    #[test]
    fn rendered_issue_toml_reads_back_to_the_same_metadata() {
        let mut metadata = IssueFields::parse(
            "date = 1803-07-04\nvolume = \"18\"\nissue = \"25\"\n\
             supplement_title = \"Supplement to \\\"The\\\" Gazette\"",
        )
        .unwrap()
        .resolve(Some(&title()))
        .unwrap();
        metadata.title = "The Saint John Gazette".into();

        let text = render_issue_toml(&metadata, &title(), &["Converted.".into()]);
        assert_eq!(
            text,
            "# Converted.\n\
             date = 1803-07-04\n\
             volume = \"18\"\n\
             issue = \"25\"\n\
             supplement_title = 'Supplement to \"The\" Gazette'\n\
             title = \"The Saint John Gazette\"\n"
        );
        let read_back = IssueFields::parse(&text)
            .unwrap()
            .resolve(Some(&title()))
            .unwrap();
        assert_eq!(read_back, metadata);
        assert_eq!(read_back.issue_title(), "Supplement to \"The\" Gazette");
    }
}
