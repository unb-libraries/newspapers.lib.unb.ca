//! Page numbers from page image file names, scheme `nbnp-v1`.
//!
//! `003_ARG_1879_01_39_03.jpg` is split on `_`; the sixth field (`03`) is the page number, or the
//! seventh when the sixth is `sup` (any case), as in `001_SJG_1803_18_25_Sup_02.jpg`. Fields after
//! the page number are ignored. This is what the PHP importer did, so page numbers on the site
//! match.

use super::metadata::limits;

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
pub fn page_number(stem: &str) -> Result<&str, PageNameError> {
    let fields: Vec<&str> = stem.split('_').collect();
    if fields.len() < 6 {
        return Err(PageNameError::TooFewFields(fields.len()));
    }
    let page = match fields.get(6) {
        Some(after) if fields[5].trim().eq_ignore_ascii_case("sup") && !after.trim().is_empty() => {
            after
        }
        _ => fields[5],
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

    #[test]
    fn the_sixth_field_is_the_page() {
        assert_eq!(page_number("003_ARG_1879_01_39_03"), Ok("03"));
        assert_eq!(page_number("003_ARG_1879_01_39_03_extra"), Ok("03"));
        assert_eq!(page_number("003_ARG_1879_01_39_ 3 "), Ok("3"));
    }

    #[test]
    fn supplements_take_the_seventh() {
        assert_eq!(page_number("001_SJG_1803_18_25_Sup_02"), Ok("02"));
        assert_eq!(page_number("001_SJG_1803_18_25_SUP_02"), Ok("02"));
        // As in PHP: `sup` with nothing after it is itself the page number.
        assert_eq!(page_number("001_SJG_1803_18_25_sup"), Ok("sup"));
        assert_eq!(page_number("001_SJG_1803_18_25_sup_"), Ok("sup"));
    }

    #[test]
    fn malformed_names_are_errors() {
        assert_eq!(
            page_number("ARG_1879_01_39_03"),
            Err(PageNameError::TooFewFields(5))
        );
        assert_eq!(
            page_number("003_ARG_1879_01_39_"),
            Err(PageNameError::EmptyPageNumber)
        );
        assert!(matches!(
            page_number("003_ARG_1879_01_39_0 3"),
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
