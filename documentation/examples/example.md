## 19872: Digital issues under incorrect title
### Morning Star -> The Star

> Morning Star, https://newspapers.lib.unb.ca/newspaper/morning-star published from Oct 10, 1878 - Apr 26, 1879, digital issues Oct 10, 1878 - Dec 30, 1879; Aug-Dec 1879 should be moved to The Star (Fredericton) https://newspapers.lib.unb.ca/newspaper/star-fredericton-new-brunswick-1879

SQL
```
SELECT id FROM digital_serial_issue WHERE parent_title=126 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1879-08-01 00:00:01' AND '1879-12-31 23:59:59');
```

_move.php_:
```
<?php

_newspapers_core_query_issues_to_title("SELECT id FROM digital_serial_issue WHERE parent_title=126 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1879-08-01 00:00:01' AND '1879-12-31 23:59:59')", 91);
_newspapers_core_update_holding_records([126, 91]);
_newspapers_core_reindex_title_issues([126, 91]);
```

```
drush scr move.php
```

<hr><hr>
### Glassville News and Aberdeen and Kent Pioneer -> The Glassville News
> Glassville News and Aberdeen and Kent Pioneer https://newspapers.lib.unb.ca/newspaper/glassville-news-and-aberdeen-and-kent-pioneer published from Jan? 1893 - Aug 15, 1893, digital issues Mar 15, 1893 - May 1, 1896, Sept. 1893-May 1896 should be moved to The Glassville News https://newspapers.lib.unb.ca/newspaper/glassville-news

_move.php_:
```
<?php

_newspapers_core_query_issues_to_title("SELECT id FROM digital_serial_issue WHERE parent_title=121 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1893-09-01 00:00:01' AND '1896-05-31 23:59:59')", 77);
_newspapers_core_update_holding_records([121, 77]);
_newspapers_core_reindex_title_issues([121, 77]);
```

```
drush scr move.php
```
