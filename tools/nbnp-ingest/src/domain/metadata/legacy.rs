//! Reads the legacy `metadata.php` without running it.
//!
//! The file is PHP source that the old importer ran with `eval` as root. Only what those files
//! contain is accepted: `define('NAME', 'value');` and one
//! `define('ISSUE_DATE', mktime(h, i, s, day, month, year));`, with comments and blank lines.
//! Anything else is an error, so nothing in the file can do more than set a value.
//!
//! The `mktime` arguments are written day before month, the reverse of PHP's order; the old
//! importer swapped them back before running the file. Dates are then normalised the way PHP's
//! `mktime` did (day 32 of January is February 1), so they match what was imported.

use time::{Date, Duration, Month};

use super::IssueFields;

/// A `metadata.php` read into issue fields, with anything PHP would have read differently.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Legacy {
    pub fields: IssueFields,
    /// The `mktime(...)` call as written, for notes.
    pub date_source: String,
    pub warnings: Vec<String>,
}

#[derive(Debug, Clone, PartialEq, Eq, thiserror::Error)]
pub enum LegacyError {
    #[error("not UTF-8 text (line {line})")]
    NotUtf8 { line: usize },
    #[error("line {line}: {message}")]
    Syntax { line: usize, message: String },
    #[error("missing {}", .0.join(", "))]
    Missing(Vec<&'static str>),
}

/// Constants the old importer read, and the issue field each one sets.
const FIELDS: [(&str, Field); 10] = [
    ("ISSUE_TITLE", Field::Title),
    ("ISSUE_DATE", Field::Date),
    ("ISSUE_VOLUME", Field::Volume),
    ("ISSUE_ISSUE", Field::Issue),
    ("ISSUE_EDITION", Field::Edition),
    ("ISSUE_SUPPLEMENT_TITLE", Field::SupplementTitle),
    ("MISSING_PAGES", Field::MissingPages),
    ("ISSUE_ERRATA", Field::Errata),
    ("ISSUE_LANGUAGE", Field::Language),
    ("SOURCE_MEDIA", Field::Media),
];

/// Islandora-era constants nothing reads any more.
const IGNORED: [&str; 2] = ["ISSUE_CONTENT_MODEL_PID", "PAGE_CONTENT_MODEL_PID"];

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum Field {
    Title,
    Date,
    Volume,
    Issue,
    Edition,
    SupplementTitle,
    MissingPages,
    Errata,
    Language,
    Media,
}

/// Reads a `metadata.php`. Every one of the ten constants must be defined, as the old importer
/// failed without them.
pub fn parse(bytes: &[u8]) -> Result<Legacy, LegacyError> {
    let bytes = bytes.strip_prefix(b"\xEF\xBB\xBF").unwrap_or(bytes);
    let text = std::str::from_utf8(bytes).map_err(|error| LegacyError::NotUtf8 {
        line: line_at(bytes, error.valid_up_to()),
    })?;
    let tokens = Lexer::new(text).tokens()?;
    Reader::new(tokens).read()
}

fn line_at(bytes: &[u8], offset: usize) -> usize {
    bytes[..offset].split(|&b| b == b'\n').count()
}

#[derive(Debug, Clone, PartialEq, Eq)]
enum Token {
    Name(String),
    Str(String),
    /// The literal as written, so octal and leading zeros can be reported.
    Int(String),
    Open,
    Close,
    Comma,
    Semicolon,
}

struct Lexer<'a> {
    rest: &'a str,
    line: usize,
}

impl<'a> Lexer<'a> {
    fn new(text: &'a str) -> Self {
        let text = text.trim_start();
        // With or without the opening tag: the old wrapper script added it when missing.
        let text = text
            .strip_prefix("<?php")
            .filter(|rest| rest.starts_with(char::is_whitespace))
            .unwrap_or(text);
        Self {
            rest: text,
            line: 1,
        }
    }

    fn error(&self, message: impl Into<String>) -> LegacyError {
        LegacyError::Syntax {
            line: self.line,
            message: message.into(),
        }
    }

    fn advance(&mut self, len: usize) -> &'a str {
        let (taken, rest) = self.rest.split_at(len);
        self.line += taken.matches('\n').count();
        self.rest = rest;
        taken
    }

    fn tokens(mut self) -> Result<Vec<(usize, Token)>, LegacyError> {
        let mut tokens = Vec::new();
        loop {
            self.skip_space_and_comments()?;
            let Some(c) = self.rest.chars().next() else {
                return Ok(tokens);
            };
            let line = self.line;
            let token = match c {
                '(' => self.single(Token::Open),
                ')' => self.single(Token::Close),
                ',' => self.single(Token::Comma),
                ';' => self.single(Token::Semicolon),
                '\'' => Token::Str(self.single_quoted()?),
                '"' => Token::Str(self.double_quoted()?),
                '-' | '0'..='9' => Token::Int(self.int()?),
                c if c.is_ascii_alphabetic() || c == '_' => {
                    let len = self
                        .rest
                        .find(|c: char| !(c.is_ascii_alphanumeric() || c == '_'))
                        .unwrap_or(self.rest.len());
                    Token::Name(self.advance(len).to_owned())
                }
                '?' if self.rest.starts_with("?>") && self.rest[2..].trim().is_empty() => {
                    self.advance(self.rest.len());
                    continue;
                }
                other => return Err(self.error(format!("unexpected {other:?}"))),
            };
            tokens.push((line, token));
        }
    }

    fn single(&mut self, token: Token) -> Token {
        self.advance(1);
        token
    }

    fn skip_space_and_comments(&mut self) -> Result<(), LegacyError> {
        loop {
            let trimmed = self.rest.trim_start();
            self.advance(self.rest.len() - trimmed.len());
            if self.rest.starts_with("//") || self.rest.starts_with('#') {
                let len = self.rest.find('\n').unwrap_or(self.rest.len());
                if self.rest[..len].contains("?>") {
                    return Err(self.error("a comment containing ?> ends the PHP code"));
                }
                self.advance(len);
            } else if self.rest.starts_with("/*") {
                let Some(end) = self.rest.find("*/") else {
                    return Err(self.error("unterminated /* comment"));
                };
                self.advance(end + 2);
            } else {
                return Ok(());
            }
        }
    }

    /// PHP single quotes: `\'` and `\\` are escapes, every other backslash is literal.
    fn single_quoted(&mut self) -> Result<String, LegacyError> {
        let start_line = self.line;
        let mut value = String::new();
        let mut chars = self.rest.char_indices().skip(1);
        while let Some((i, c)) = chars.next() {
            match c {
                '\'' => {
                    self.advance(i + 1);
                    return Ok(value);
                }
                '\\' => match chars.next() {
                    Some((_, escaped @ ('\'' | '\\'))) => value.push(escaped),
                    Some((_, other)) => {
                        value.push('\\');
                        value.push(other);
                    }
                    None => break,
                },
                c => value.push(c),
            }
        }
        self.line = start_line;
        Err(self.error("unterminated string"))
    }

    /// PHP double quotes interpolate and escape; only strings needing neither are accepted.
    fn double_quoted(&mut self) -> Result<String, LegacyError> {
        let Some(end) = self.rest[1..].find('"') else {
            return Err(self.error("unterminated string"));
        };
        let value = &self.rest[1..=end];
        if value.contains(['\\', '$']) {
            return Err(self.error(
                "a double-quoted string with \\ or $ is read differently by PHP; use single quotes",
            ));
        }
        let value = value.to_owned();
        self.advance(end + 2);
        Ok(value)
    }

    fn int(&mut self) -> Result<String, LegacyError> {
        let digits_from = usize::from(self.rest.starts_with('-'));
        let len = self.rest[digits_from..]
            .find(|c: char| !c.is_ascii_digit())
            .map_or(self.rest.len(), |n| n + digits_from);
        if len == digits_from {
            return Err(self.error("expected a number after -"));
        }
        Ok(self.advance(len).to_owned())
    }
}

struct Reader {
    tokens: std::vec::IntoIter<(usize, Token)>,
    line: usize,
    fields: IssueFields,
    seen: Vec<&'static str>,
    date_source: String,
    warnings: Vec<String>,
}

impl Reader {
    fn new(tokens: Vec<(usize, Token)>) -> Self {
        Self {
            tokens: tokens.into_iter(),
            line: 1,
            fields: IssueFields::default(),
            seen: Vec::new(),
            date_source: String::new(),
            warnings: Vec::new(),
        }
    }

    fn error(&self, message: impl Into<String>) -> LegacyError {
        LegacyError::Syntax {
            line: self.line,
            message: message.into(),
        }
    }

    fn next(&mut self) -> Result<Token, LegacyError> {
        let (line, token) = self
            .tokens
            .next()
            .ok_or_else(|| self.error("the file ends in the middle of a define"))?;
        self.line = line;
        Ok(token)
    }

    /// A missing `,`, `)` or `;` is reported on the line of what came before it.
    fn expect(&mut self, wanted: &Token, what: &str) -> Result<(), LegacyError> {
        let before = self.line;
        let token = self.next()?;
        if &token == wanted {
            Ok(())
        } else {
            self.line = before;
            Err(self.error(format!("expected {what}, found {}", describe(&token))))
        }
    }

    fn function(&mut self, name: &str) -> Result<(), LegacyError> {
        match self.next()? {
            Token::Name(found) if found.eq_ignore_ascii_case(name) => {
                self.expect(&Token::Open, "(")
            }
            other => Err(self.error(format!("expected {name}(, found {}", describe(&other)))),
        }
    }

    fn read(mut self) -> Result<Legacy, LegacyError> {
        while self.tokens.len() > 0 {
            self.statement()?;
        }
        let missing: Vec<_> = FIELDS
            .iter()
            .map(|(name, _)| *name)
            .filter(|name| !self.seen.contains(name))
            .collect();
        if !missing.is_empty() {
            return Err(LegacyError::Missing(missing));
        }
        Ok(Legacy {
            fields: self.fields,
            date_source: self.date_source,
            warnings: self.warnings,
        })
    }

    /// `define('NAME', value);`
    fn statement(&mut self) -> Result<(), LegacyError> {
        self.function("define")?;
        let name = match self.next()? {
            Token::Str(name) => name,
            other => {
                return Err(self.error(format!(
                    "expected a quoted constant name, found {}",
                    describe(&other)
                )));
            }
        };
        self.expect(&Token::Comma, ",")?;

        if IGNORED.contains(&name.as_str()) {
            self.string_value()?;
        } else {
            let Some(&(known, field)) = FIELDS.iter().find(|(known, _)| *known == name) else {
                return Err(self.error(format!("unknown constant {name:?}")));
            };
            if self.seen.contains(&known) {
                return Err(self.error(format!("{known} is defined twice")));
            }
            self.seen.push(known);
            if field == Field::Date {
                self.fields.date = Some(self.mktime()?);
            } else {
                let value = self.string_value()?;
                self.set(field, value);
            }
        }
        self.expect(&Token::Close, ")")?;
        self.expect(&Token::Semicolon, ";")
    }

    fn string_value(&mut self) -> Result<String, LegacyError> {
        match self.next()? {
            Token::Str(value) => Ok(value),
            other => Err(self.error(format!(
                "expected a quoted value, found {}",
                describe(&other)
            ))),
        }
    }

    fn set(&mut self, field: Field, value: String) {
        let slot = match field {
            Field::Title => &mut self.fields.title,
            Field::Volume => &mut self.fields.volume,
            Field::Issue => &mut self.fields.issue,
            Field::Edition => &mut self.fields.edition,
            Field::SupplementTitle => &mut self.fields.supplement_title,
            Field::MissingPages => &mut self.fields.missing_pages,
            Field::Errata => &mut self.fields.errata,
            Field::Language => &mut self.fields.language,
            Field::Media => &mut self.fields.media,
            Field::Date => unreachable!("dates are read by mktime"),
        };
        *slot = Some(value);
    }

    /// `mktime(hour, minute, second, day, month, year)`, day and month as these files write them.
    fn mktime(&mut self) -> Result<Date, LegacyError> {
        let call_line = self.line;
        self.function("mktime")?;
        let mut args = [0_i64; 6];
        let mut written = Vec::with_capacity(6);
        for (i, arg) in args.iter_mut().enumerate() {
            if i > 0 {
                self.expect(&Token::Comma, ",")?;
            }
            let literal = match self.next()? {
                Token::Int(literal) => literal,
                other => {
                    return Err(self.error(format!(
                        "mktime takes six whole numbers, found {}",
                        describe(&other)
                    )));
                }
            };
            *arg = self.php_int(&literal)?;
            written.push(literal);
        }
        self.expect(&Token::Close, ")")?;
        self.date_source = format!("mktime({})", written.join(", "));

        let [hour, minute, second, day, month, year] = args;
        let year = match year {
            0..=69 => year + 2000,
            70..=100 => year + 1900,
            _ => year,
        };
        if year != args[5] {
            self.warnings.push(format!(
                "line {call_line}: PHP reads year {} as {year}",
                args[5]
            ));
        }
        let date = normalise(year, month, day, hour * 3600 + minute * 60 + second)
            .ok_or_else(|| self.error(format!("{} is not a date", self.date_source)))?;
        let written_date = (year, month, day);
        if written_date
            != (
                i64::from(date.year()),
                i64::from(u8::from(date.month())),
                i64::from(date.day()),
            )
            || (hour, minute, second) != (0, 0, 0)
        {
            self.warnings.push(format!(
                "line {call_line}: {} is not a calendar date at midnight; PHP read it as {date}",
                self.date_source
            ));
        }
        Ok(date)
    }

    /// The value PHP gives an integer literal: a leading zero means octal, and PHP refuses 08
    /// and 09 outright.
    fn php_int(&mut self, literal: &str) -> Result<i64, LegacyError> {
        let (negative, digits) = literal
            .strip_prefix('-')
            .map_or((false, literal), |digits| (true, digits));
        let octal = digits.len() > 1 && digits.starts_with('0');
        let value = if octal && digits.contains(['8', '9']) {
            self.warnings.push(format!(
                "line {}: PHP cannot read {literal}, so the old importer failed on this file; \
                 read here as {}",
                self.line,
                digits.trim_start_matches('0')
            ));
            digits.parse::<i64>()
        } else if octal {
            let value = i64::from_str_radix(digits, 8);
            if value
                .as_ref()
                .is_ok_and(|v| v.to_string() != digits.trim_start_matches('0'))
            {
                self.warnings.push(format!(
                    "line {}: PHP reads {literal} as octal, {}",
                    self.line,
                    value.as_ref().unwrap_or(&0)
                ));
            }
            value
        } else {
            digits.parse::<i64>()
        }
        .map_err(|_| self.error(format!("{literal} is too large")))?;
        Ok(if negative { -value } else { value })
    }
}

/// The date PHP's `mktime` gives: months past 12 and days past the month's end roll forward,
/// zero and negative values roll back.
fn normalise(year: i64, month: i64, day: i64, seconds: i64) -> Option<Date> {
    let months = year.checked_mul(12)?.checked_add(month.checked_sub(1)?)?;
    let first = Date::from_calendar_date(
        i32::try_from(months.div_euclid(12)).ok()?,
        Month::try_from(u8::try_from(months.rem_euclid(12) + 1).ok()?).ok()?,
        1,
    )
    .ok()?;
    let days = day
        .checked_sub(1)?
        .checked_add(seconds.div_euclid(86_400))?;
    first.checked_add(Duration::days(days))
}

fn describe(token: &Token) -> String {
    match token {
        Token::Name(name) => name.clone(),
        Token::Str(value) => format!("{value:?}"),
        Token::Int(literal) => literal.clone(),
        Token::Open => "(".into(),
        Token::Close => ")".into(),
        Token::Comma => ",".into(),
        Token::Semicolon => ";".into(),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// `/mnt/nbnp/The_News/TN_1909/TN_1909_33/TN_1909_33_05/metadata.php`, as pasted.
    const THE_NEWS: &str = "define('ISSUE_DATE', mktime(0, 0, 0, 29, 1, 1909));
define('ISSUE_TITLE','The News');
define('ISSUE_VOLUME', '33');
define('ISSUE_ISSUE', '5');
define('ISSUE_EDITION', '');
define('ISSUE_SUPPLEMENT_TITLE', '');
define('MISSING_PAGES', '');
define('ISSUE_ERRATA','');

define('ISSUE_CONTENT_MODEL_PID', 'islandora:newspaperIssueCModel');
define('PAGE_CONTENT_MODEL_PID', 'islandora:newspaperPageCModel');
define('ISSUE_LANGUAGE', 'eng');
define('SOURCE_MEDIA','print');

// ** Save this file as metadata.php
";

    /// The supplement `SJG_1803_18_25_Sup/metadata.php`, as pasted.
    const SUPPLEMENT: &str = "define('ISSUE_DATE', mktime(0, 0, 0, 4, 7, 1803));
define('ISSUE_TITLE','The Saint John Gazette');
define('ISSUE_VOLUME', '18');
define('ISSUE_ISSUE', '25');
define('ISSUE_EDITION', '');
define('ISSUE_SUPPLEMENT_TITLE', 'Supplement to The Saint John Gazette');
define('MISSING_PAGES', '');
define('ISSUE_ERRATA','');

define('ISSUE_CONTENT_MODEL_PID', 'islandora:newspaperIssueCModel');
define('PAGE_CONTENT_MODEL_PID', 'islandora:newspaperPageCModel');
define('ISSUE_LANGUAGE', 'eng');
define('SOURCE_MEDIA','print');

// ** Save this file as metadata.php
";

    fn with_date(mktime: &str) -> String {
        THE_NEWS.replace("mktime(0, 0, 0, 29, 1, 1909)", mktime)
    }

    fn date_of(text: &str) -> (String, Vec<String>) {
        let legacy = parse(text.as_bytes()).unwrap();
        (legacy.fields.date.unwrap().to_string(), legacy.warnings)
    }

    #[test]
    fn reads_the_news_sample() {
        let legacy = parse(THE_NEWS.as_bytes()).unwrap();
        let f = &legacy.fields;
        assert_eq!(f.date.unwrap().to_string(), "1909-01-29");
        assert_eq!(f.title.as_deref(), Some("The News"));
        assert_eq!(f.volume.as_deref(), Some("33"));
        assert_eq!(f.issue.as_deref(), Some("5"));
        assert_eq!(f.edition.as_deref(), Some(""));
        assert_eq!(f.language.as_deref(), Some("eng"));
        assert_eq!(f.media.as_deref(), Some("print"));
        assert_eq!(f.title_id, None);
        assert_eq!(legacy.date_source, "mktime(0, 0, 0, 29, 1, 1909)");
        assert!(legacy.warnings.is_empty());
    }

    #[test]
    fn reads_the_supplement_sample() {
        let legacy = parse(SUPPLEMENT.as_bytes()).unwrap();
        assert_eq!(legacy.fields.date.unwrap().to_string(), "1803-07-04");
        assert_eq!(
            legacy.fields.supplement_title.as_deref(),
            Some("Supplement to The Saint John Gazette")
        );
    }

    #[test]
    fn day_and_month_are_written_reversed() {
        // The third sample, TN_1909_33_15: issue 15 of a weekly, ten weeks after issue 5.
        assert_eq!(
            date_of(&with_date("mktime(0, 0, 0, 9, 4, 1909)")).0,
            "1909-04-09"
        );
    }

    #[test]
    fn opening_tag_bom_and_closing_tag_are_accepted() {
        let text = format!("\u{feff}<?php\n{THE_NEWS}?>\n");
        assert_eq!(date_of(&text).0, "1909-01-29");
    }

    #[test]
    fn out_of_range_dates_roll_over_like_php() {
        let (date, warnings) = date_of(&with_date("mktime(0, 0, 0, 31, 4, 1909)"));
        assert_eq!(date, "1909-05-01");
        assert_eq!(warnings.len(), 1);
        assert!(
            warnings[0].contains("PHP read it as 1909-05-01"),
            "{warnings:?}"
        );

        assert_eq!(
            date_of(&with_date("mktime(0, 0, 0, 0, 1, 1909)")).0,
            "1908-12-31"
        );
        assert_eq!(
            date_of(&with_date("mktime(0, 0, 0, 1, 13, 1909)")).0,
            "1910-01-01"
        );
        assert_eq!(
            date_of(&with_date("mktime(24, 0, 0, 28, 2, 1909)")).0,
            "1909-03-01"
        );
    }

    #[test]
    fn leading_zeros_are_read_as_php_did() {
        let (date, warnings) = date_of(&with_date("mktime(0, 0, 0, 07, 01, 1909)"));
        assert_eq!(date, "1909-01-07");
        assert!(warnings.is_empty(), "{warnings:?}");

        let (date, warnings) = date_of(&with_date("mktime(0, 0, 0, 010, 1, 1909)"));
        assert_eq!(date, "1909-01-08");
        assert!(warnings[0].contains("octal, 8"), "{warnings:?}");

        let (date, warnings) = date_of(&with_date("mktime(0, 0, 0, 09, 1, 1909)"));
        assert_eq!(date, "1909-01-09");
        assert!(warnings[0].contains("PHP cannot read 09"), "{warnings:?}");
    }

    #[test]
    fn two_digit_years_follow_php() {
        let (date, warnings) = date_of(&with_date("mktime(0, 0, 0, 1, 1, 9)"));
        assert_eq!(date, "2009-01-01");
        assert!(warnings[0].contains("year 9 as 2009"), "{warnings:?}");
    }

    #[test]
    fn single_quote_escapes_follow_php() {
        let text = THE_NEWS.replace("'The News'", r"'The Editor\'s \\News\n'");
        let legacy = parse(text.as_bytes()).unwrap();
        assert_eq!(
            legacy.fields.title.as_deref(),
            Some(r"The Editor's \News\n")
        );
    }

    #[test]
    fn plain_double_quotes_are_accepted() {
        let text = THE_NEWS.replace("'The News'", "\"The News\"");
        assert!(parse(text.as_bytes()).is_ok());
        let text = THE_NEWS.replace("'The News'", "\"The $News\"");
        assert!(matches!(
            parse(text.as_bytes()),
            Err(LegacyError::Syntax { line: 2, .. })
        ));
    }

    #[test]
    fn anything_but_defines_is_refused() {
        for (text, line) in [
            (format!("{THE_NEWS}system('rm -rf /');\n"), 16),
            (format!("{THE_NEWS}define('OTHER', 'x');\n"), 16),
            (THE_NEWS.replace("'33'", "33"), 3),
            (THE_NEWS.replace("'33'", "'3' . '3'"), 3),
            (THE_NEWS.replace("mktime(", "time("), 1),
            (format!("{THE_NEWS}define('ISSUE_ISSUE', '6');\n"), 16),
            (THE_NEWS.replace("'5');", "'5')"), 4),
        ] {
            match parse(text.as_bytes()) {
                Err(LegacyError::Syntax { line: found, .. }) => assert_eq!(found, line, "{text}"),
                other => panic!("expected a syntax error on line {line}, got {other:?}"),
            }
        }
    }

    #[test]
    fn every_constant_is_required() {
        let text = THE_NEWS.replace("define('ISSUE_ERRATA','');\n", "");
        assert_eq!(
            parse(text.as_bytes()),
            Err(LegacyError::Missing(vec!["ISSUE_ERRATA"]))
        );
    }

    #[test]
    fn non_utf8_is_reported_with_its_line() {
        let mut bytes = THE_NEWS.as_bytes().to_vec();
        let at = THE_NEWS.find("The News").unwrap();
        bytes[at] = 0xE9;
        assert_eq!(parse(&bytes), Err(LegacyError::NotUtf8 { line: 2 }));
    }
}
