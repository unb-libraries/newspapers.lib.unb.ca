# NBNP Issue Operations

## 1. Issue Import
### Opening a Screen
1. Determine the full path on _MANTICORE_ to parse for issues. Although the tool can import thousands of issues at once, it is recommended to break the import up into smaller groups (such as one year's issues).
   * Example: ```/mnt/nbnp/TheWeeklyChronicle/WC_1824/``` (**include** the trailing slash)
2. Determine the NBNP title ID that the issues will be attached to.
   * The title ID can be found by visiting https://newspapers.lib.unb.ca/, performing a 'title search', clicking on the 'Digital Issues at UNB Libraries' statement/hyperlink and noting the ID in the URL:
     * Example: from the URL ```https://newspapers.lib.unb.ca/serials/browse/48```, use ```48```
3. SSH to _MANTICORE_ as the 'imaging' user
4. Check to see if a 'screen' session is open for the NBNP import:
   * ```screen -x NBNP```. Two outcomes are possible here:
     1. No error will occur - you will see a screen with previous data or an empty prompt.
     2. ```There is no screen to be attached matching NBNP.``` appears on the screen. This indicates that no session is active, and you must create one via ```screen -S NBNP```
5. Change to the 'imaging' user's home directory: ```cd /home/imaging```

### Pre-Auditing The Issue Metadata:
Often, issues with metadata can be caught BEFORE running the import.

6. Audit the metadata for problems : ```./auditIssueMetadata.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/```
    * The first argument is the path to the files to import (as determined in step #1).
    * If no problems are found with the metadata, a success message will be displayed. If not, an error message, preceeded immediately by the relevant metadata.php file path will detail the problem.

### Running The Issue Import:

7. Run the import : ```./importNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 48``` 
   * The first argument is the path to the files to import (as determined in step #1).
   * The second argument is the 'parent' issue ID (as determined in step #2)
8. When presented with a confirmation screen:
```
================================================================= 
  Directory                                                        
 ================================================================= 
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2022  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2005  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2006  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2015  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2010  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2013  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2007  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2014  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2004  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2012  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2016  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2008  
  /mnt/nbnp/TheWeeklyChronicle/WC_1824/WC_1824_39/WC_1824_39_2020  
 ================================================================= 
?  The Create Issues will be applied to ALL of the above directories. Are you sure you want to continue? (y/n)
```

If everything seems kosher, type y to continue.

The import process can take hours, depending on the number of issues to import.

_It is safe to disconnect from the SSH session at this point, as the process is running inside a "screen"_.

### Check on a Running Import's Progress

1. SSH to _MANTICORE_ as the 'imaging' user
2. Connect to the 'screen' session for the NBNP import:
    * ```screen -x NBNP```. Two outcomes are possible here:
        1. No error will occur - you will see a screen with the import process or a message indicating the import was complete.
        2. ```There is no screen to be attached matching NBNP.``` appears on the screen. This indicates an import was not started or the server has been rebooted since - _screen sessions do not 'survive' reboots_. Contact Jake!

## 2. Imported Issue Validation
(determine issue data as above)
1. SSH to _MANTICORE_ as the 'imaging' user
2. Change to the 'imaging' user's home directory: ```cd /home/imaging```
3. Run the audit : ```./auditNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 48```
    * The first argument is the path to the files to import (as determined in step #1 above).
    * The second argument is the 'parent' issue ID (as determined in step #2 above)

On average, on _MANTICORE_, the audit process takes about 10 minutes per 1,000 pages.
