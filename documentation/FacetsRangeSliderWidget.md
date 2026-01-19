# Facet Range Slider Widget Issue
There have been several bug reports re: search results not filtering correctly when using the range slider facet widget.

Examples:
- https://support.lib.unb.ca/default.asp?21576
- https://support.lib.unb.ca/default.asp?21894

Essentially, the labels displayed for the range slider widget's min/max points are sometimes out of line with the actual values submitted to the search which can, of course, be misleading for the user. 

It is suspected that the source of the issue is related to a discrepancy with the mapping of large result sets to visual points on the slider widget provided by **jQuery UI Slider Pips**. This is the JS library required by the **Facets Range Widget** module. While it is compatible with Drupal 11, it is no longer actively supported (https://github.com/simeydotme/jQuery-ui-Slider-Pips).

# Proposed Workaround/Solution
Tests have suggested that the issue is associated with the reliance on the **Count limit** facet setting to hide the Facet Range Widget block for searches that return no results (see image below).
![Facet setting: Count limit of 1](images/nbnp-facet-settings-count.png)

It has been discovered that using **Hide facet with 1 result** in place of the **Count limit** facet setting also hides the facet for 0 results returned as desired and more importantly, seems to resolve the inaccurate slider widget value label (see image below).
![Facet setting: Count limit of 1](images/nbnp-facet-settings-hide.png)
