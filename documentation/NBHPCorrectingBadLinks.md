# Correcting Improperly Linked NBHP Assets
NBHP assets are linked in various ways in storage. It is important to understand the relationship and types of all assets before beginning to correct any issues. NBHP's database contains four types of newspaper-related assets:

| Entity        | Description                                                                                                                                                                                                                                                                                                                                    | Storage Type  | Bundle Name          | Database Table       |
|---------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------|----------------------|----------------------|
| Publication   | A Metadata record that contains all information and history of a newspaper title.                                                                                                                                                                                                                                                              | Node          | publication          | node                 |
| Digital Title | A 'relational' record whose only purpose is to provide a link between Digital Issues and publications.                                                                                                                                                                                                                                         | Custom Entity | digital_serial_title | digital_serial_title |
| Digital Issue | An entity that contains metadata describing a single issue of the Publication, as well as one or more Digital Pages.                                                                                                                                                                                                                           | Custom Entity | digital_serial_issue | digital_serial_issue |
| Digital Page  | An entity that contains metadata describing a single page of a Digital Issue, its textual content and an image of the page itself.                                                                                                                                                                                                             | Custom Entity | digital_serial_page  | digital_serial_page  |
| Holding       | An entity representing physical and digital holdings at various institutions. Holdings are linked to Publications and can be of many types - physical, digital, etc. A holding is always created to reference NBHP serial titles with the the holding.type = 'Digital' and the holding.coverage statement = 'Digital Issues at UNB Libraries'. | Custom Entity | serial_holding       | serial_holding       |

## Operations
### Move ALL Digital Issues Within a Digital Title To a Different Digital Title
First, move the digital issues to the new digital title:
```
drush eval '_newspapers_core_move_all_title_issues(48, 105)'
```

Then, update the holding record 'coverage' ranges for the digital serial titles:

```
drush eval '_newspapers_core_update_holding_records([48, 108])'
```

Then, re-index all the issues in solr for both of the titles, as both have changed.
```
drush eval '_newspapers_core_reindex_title_issues([48, 105])'
```

### Move ONE Digital Issue From a Digital Title To a Different Digital Title
First, find the digital title ID of the digital title that the issue is currently in. (48)

Then, move the digital issue to the new digital title:
```
drush eval '_newspapers_core_move_issue_to_title(198, 108)'
```

Then, update the holding record 'coverage' ranges for the digital serial titles:

```
drush eval '_newspapers_core_update_holding_records([48, 108])'
```

Then, re-index the issue in solr:

```
drush eval '_newspapers_core_reindex_issues(['198'])'
```

### Move MULTIPLE Digital Issues From a Digital Title To a Different Digital Title Based On A Date Range
```
drush eval "_newspapers_core_query_issues_to_title(\"SELECT id FROM digital_serial_issue WHERE parent_title=62 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1904-09-31 23:59:59' AND '1905-02-06 23:59:59')\", 108)'"
```

Then, update the holding record 'coverage' ranges for the digital serial titles:

```
drush eval '_newspapers_core_update_holding_records([62, 108])'
```

Then, it is easiest to simply re-index all the issues for both of the titles, as both have changed.

```
drush eval '_newspapers_core_reindex_title_issues([62, 108])'
```


### Link A Digital Title To a Different Publication

First, find the digital title ID of the digital title you wish to move. (48)

Then, find the publication Node ID of the publication you wish to un-link the digital tile from. (272)

Then, find the publication Node ID of the publication you wish to link the digital title to. (1468)

Then, update the digital title's parent title link to the new publication ID:
```
UPDATE digital_serial_title SET parent_title=1468 WHERE parent_title=272;
```

Then, update the existing holding records to point to the new publication ID:
```
UPDATE serial_holding SET parent_title=1468 WHERE parent_title=272 AND holding_coverage='Digital Issues at UNB Libraries';
```

Then, update the holding record 'coverage' ranges for the digital serial title:

```
drush eval '_newspapers_core_update_holding_records([48])'
```

Then, re-index all the issues for the title in solr:

```
drush eval '_newspapers_core_reindex_title_issues([48])'
```
