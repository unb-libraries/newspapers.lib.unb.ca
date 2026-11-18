//! Page numbers from page image file names.
//!
//! Names are split on `_` and need at least six fields, as in `003_ARG_1879_01_39_03.jpg`:
//! sequence, title code, year, volume, issue, page. Supplements add a marker before the page,
//! as in `002_BBJ_1986_3_17_S2_2.jpg`. The schemes differ in which field is the page number.

use serde::{Deserialize, Serialize};

use super::metadata::limits;

/// How page numbers are read from page image file names.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Deserialize, Serialize)]
pub enum Scheme {
    /// The PHP importer's rule: the sixth field, or the seventh when the sixth is exactly `sup`.
    /// Any other supplement marker (`S2`, `Sup2`, `SA`) becomes every page's number, so it is
    /// kept only to reproduce what that importer did.
    #[serde(rename = "nbnp-v1")]
    NbnpV1,
    /// The last field, whatever marks a supplement before it. A last field without digits after
    /// the page, as in `016_DM_1927_33_149_16_Sup.jpg`, is a marker, and the field before it is
    /// the page.
    #[serde(rename = "nbnp-v2")]
    NbnpV2,
}

#[derive(Debug, Clone, PartialEq, Eq, thiserror::Error)]
pub enum PageNameError {
    #[error("has {0} `_` fields; at least 6 are needed, as in 003_ARG_1879_01_39_03.jpg")]
    TooFewFields(usize),
    #[error("has an empty page number field")]
    EmptyPageNumber,
    #[error("page number {0:?} contains whitespace or control characters")]
    BadCharacters(String),
}

/// The page number written in a file name (without its extension).
pub fn page_number(scheme: Scheme, stem: &str) -> Result<&str, PageNameError> {
    let fields: Vec<&str> = stem.split('_').collect();
    if fields.len() < 6 {
        return Err(PageNameError::TooFewFields(fields.len()));
    }
    let page = match scheme {
        Scheme::NbnpV1 => match fields.get(6) {
            Some(after)
                if fields[5].trim().eq_ignore_ascii_case("sup") && !after.trim().is_empty() =>
            {
                after
            }
            _ => fields[5],
        },
        Scheme::NbnpV2 => {
            let last = fields[fields.len() - 1];
            let before = fields[fields.len() - 2];
            if fields.len() > 6
                && !last.contains(|c: char| c.is_ascii_digit())
                && !before.trim().is_empty()
            {
                before
            } else {
                last
            }
        }
    };
    let page = page.trim_matches(php_trim);
    if page.is_empty() {
        return Err(PageNameError::EmptyPageNumber);
    }
    if page.chars().any(|c| c.is_whitespace() || c.is_control()) {
        return Err(PageNameError::BadCharacters(page.to_owned()));
    }
    Ok(page)
}

/// The characters PHP's `trim` removes.
fn php_trim(c: char) -> bool {
    matches!(c, ' ' | '\t' | '\n' | '\r' | '\0' | '\x0B')
}

/// The sort key the site orders pages by: the page number left-padded with zeros to 4 characters.
#[must_use]
pub fn page_sort(page_number: &str) -> String {
    format!("{page_number:0>4}")
}

/// Gives every page in an issue a distinct page number, in the order given: the second `03` is
/// `03_0`, the third `03_1`, as the PHP importer numbered them. `taken` holds numbers already used,
/// for example by pages already on the site.
pub fn distinct_page_numbers<'a>(
    numbers: impl IntoIterator<Item = &'a str>,
    taken: &[String],
) -> Vec<String> {
    let mut used: Vec<String> = taken.to_vec();
    let mut out = Vec::new();
    for number in numbers {
        let mut candidate = number.to_owned();
        let mut counter = 0_usize;
        while used.contains(&candidate) {
            candidate = format!("{number}_{counter}");
            counter += 1;
        }
        used.push(candidate.clone());
        out.push(candidate);
    }
    out
}

/// A page number the site cannot store.
#[must_use]
pub fn too_long(page_number: &str) -> bool {
    page_number.chars().count() > limits::PAGE_NO
}

#[cfg(test)]
mod tests {
    use super::*;

    use Scheme::{NbnpV1, NbnpV2};

    #[test]
    fn v2_takes_the_last_field() {
        assert_eq!(page_number(NbnpV2, "003_ARG_1879_01_39_03"), Ok("03"));
        assert_eq!(page_number(NbnpV2, "002_BBJ_1986_3_17_S2_2"), Ok("2"));
        assert_eq!(page_number(NbnpV2, "002_ABR_1992_01_03_Sup2_02"), Ok("02"));
        assert_eq!(
            page_number(NbnpV2, "002_RW_1990_07_42_Sup_A_002"),
            Ok("002")
        );
        assert_eq!(page_number(NbnpV2, "001_SJG_1803_18_25_Sup_02"), Ok("02"));
        assert_eq!(page_number(NbnpV2, "016_DM_1927_33_149_16_Sup"), Ok("16"));
        // Six fields: the last is the page even without digits; check warns about it.
        assert_eq!(page_number(NbnpV2, "001_DM_1927_33_149_Sup"), Ok("Sup"));
        assert_eq!(
            page_number(NbnpV2, "003_ARG_1879_01_39_"),
            Err(PageNameError::EmptyPageNumber)
        );
        assert_eq!(
            page_number(NbnpV2, "ARG_1879_01_39_03"),
            Err(PageNameError::TooFewFields(5))
        );
    }

    #[test]
    fn v1_gives_supplement_markers_as_page_numbers() {
        assert_eq!(page_number(NbnpV1, "002_BBJ_1986_3_17_S2_2"), Ok("S2"));
    }

    #[test]
    fn the_sixth_field_is_the_page() {
        assert_eq!(
            page_number(Scheme::NbnpV1, "003_ARG_1879_01_39_03"),
            Ok("03")
        );
        assert_eq!(
            page_number(Scheme::NbnpV1, "003_ARG_1879_01_39_03_extra"),
            Ok("03")
        );
        assert_eq!(
            page_number(Scheme::NbnpV1, "003_ARG_1879_01_39_ 3 "),
            Ok("3")
        );
    }

    #[test]
    fn supplements_take_the_seventh() {
        assert_eq!(
            page_number(Scheme::NbnpV1, "001_SJG_1803_18_25_Sup_02"),
            Ok("02")
        );
        assert_eq!(
            page_number(Scheme::NbnpV1, "001_SJG_1803_18_25_SUP_02"),
            Ok("02")
        );
        // As in PHP: `sup` with nothing after it is itself the page number.
        assert_eq!(
            page_number(Scheme::NbnpV1, "001_SJG_1803_18_25_sup"),
            Ok("sup")
        );
        assert_eq!(
            page_number(Scheme::NbnpV1, "001_SJG_1803_18_25_sup_"),
            Ok("sup")
        );
    }

    #[test]
    fn malformed_names_are_errors() {
        assert_eq!(
            page_number(Scheme::NbnpV1, "ARG_1879_01_39_03"),
            Err(PageNameError::TooFewFields(5))
        );
        assert_eq!(
            page_number(Scheme::NbnpV1, "003_ARG_1879_01_39_"),
            Err(PageNameError::EmptyPageNumber)
        );
        assert!(matches!(
            page_number(Scheme::NbnpV1, "003_ARG_1879_01_39_0 3"),
            Err(PageNameError::BadCharacters(_))
        ));
    }

    #[test]
    fn page_sort_pads_to_four() {
        assert_eq!(page_sort("3"), "0003");
        assert_eq!(page_sort("03"), "0003");
        assert_eq!(page_sort("12a"), "012a");
        assert_eq!(page_sort("12345"), "12345");
    }

    #[test]
    fn repeated_numbers_get_suffixes_like_php() {
        assert_eq!(
            distinct_page_numbers(["01", "03", "03", "03"], &[]),
            ["01", "03", "03_0", "03_1"]
        );
        assert_eq!(
            distinct_page_numbers(["03"], &["03".into(), "03_0".into()]),
            ["03_1"]
        );
    }
}
