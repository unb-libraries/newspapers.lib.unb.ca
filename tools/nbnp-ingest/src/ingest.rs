//! What `audit` and `import` share: reading an issue directory ready to be matched with the site,
//! and finding its issue there. Each command decides what a result means for it.

pub mod execute;
pub mod prepare;
pub mod resolve;
