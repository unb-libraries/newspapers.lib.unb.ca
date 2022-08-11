# Correcting Improperly Linked NBHP Assets
NBHP assets are linked in various ways in storage. It is important to understand the relationship and types of all assets before beginning to correct any issues. NBHP's database contains four types of newspaper-related assets:

| Asset         | Description                                                                                                                        | Storage Type  | Bundle Name          | Database Table       |
|---------------|------------------------------------------------------------------------------------------------------------------------------------|---------------|----------------------|----------------------|
| Publication   | A Metadata record that contains all information and history of a newspaper title.                                                  | Node          | publication          | node                 |
| Digital Title | A 'relational' record whose only purpose is to provide a link between Digital Issues and publications.                             | Custom Entity | digital_serial_title | digital_serial_title |
| Digital Issue | An entity that contains metadata describing a single issue of the Publication, as well as one or more Digital Pages.               | Custom Entity | digital_serial_issue | digital_serial_issue |
| Digital Page  | An entity that contains metadata describing a single page of a Digital Issue, its textual content and an image of the page itself. | Custom Entity | digital_serial_page  | digital_serial_page  |

## Operations
### Move ALL Digital Issues Within a Digital Title To a Different Digital Title

```
drush eval '_newspapers_core_move_all_title_issues(48, 105)'
```

### Move ONE Digital Issue From a Digital Title To a Different Digital Title

```
drush eval '_newspapers_core_move_issue_to_title(198, 108)'
```

### Move MULTIPLE Digital Issues From a Digital Title To a Different Digital Title Based On A Date Range
```
drush eval "_newspapers_core_query_issues_to_title(\"SELECT id FROM digital_serial_issue WHERE parent_title=62 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1904-09-31 23:59:59' AND '1905-02-06 23:59:59')\", 108)'
```

### Link A Digital Title To a Different Publication

```
UPDATE digital_serial_title SET parent_title=1468 WHERE parent_title=272;
UPDATE serial_holding SET parent_title=1468 WHERE parent_title=272 AND holding_coverage='Digital Issues at UNB Libraries';
```

The above IDs are publication IDs, obtain the old and new digital_serial_title IDs and run: 

```
drush eval '_newspapers_core_update_holding_records([45])'
```
