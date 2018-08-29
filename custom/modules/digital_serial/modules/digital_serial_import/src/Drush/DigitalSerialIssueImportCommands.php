<?php

namespace Drupal\digital_serial_import\Drush;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;
use Drupal\digital_serial_issue\Entity\SerialIssue;
use Drupal\digital_serial_page\Entity\SerialPage;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

/**
 * A Drush commandfile.
 */
class DigitalSerialIssueImportCommands extends DrushCommands {

  /**
   * The batch array.
   *
   * @var array
   */
  protected $batch = [];

  /**
   * The files to import.
   *
   * @var array
   */
  protected $files = [];

  /**
   * The image extension to filter on.
   *
   * @var string
   */
  protected $imageExtension = NULL;

  /**
   * The files to import.
   *
   * @var \Drupal\digital_serial_issue\Entity\SerialIssue
   */
  protected $issue = NULL;

  /**
   * The options passed to the command.
   *
   * @var array
   */
  protected $options = [];

  /**
   * The parent digital serial title.
   *
   * @var int
   */
  protected $parentSerialId = 0;

  /**
   * The parent digital serial title.
   *
   * @var \Drupal\digital_serial_title\Entity\SerialTitle
   */
  protected $parentSerialTitle = NULL;

  /**
   * The source directory.
   *
   * @var string
   */
  protected $sourceDirectory = NULL;

  /**
   * Import a directory of images as a digital serial issue.
   *
   * @param string $source_directory
   *   The source directory to pull the images from.
   * @param int $parent_serial_id
   *   The parent digital serial title ID.
   * @param string $image_extension
   *   The file extension to consider as an image. All files with this extension
   *   will be considered pages. Defaults to tif.
   *
   * @option issue-timestamp
   *   Whether or not the second parameter should come first in the result.
   * @option issue-title
   *   The issue title to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-volume
   *   The issue volume to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-issue
   *   The issue number to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-edition
   *   The issue edition to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-supplement-title
   *   The issue supplement title to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-missing-pages
   *   The issue missing pages value to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-errata
   *   The issue errata to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-source-media
   *   The source media value to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option issue-language
   *   The language value to use when creating the issue. Ignored if
   *   --existing-issue-id is passed.
   * @option existing-issue-id
   *   The issue ID to add the images to. Does not create a new issue.
   *
   * @command digital-serial-import:import-issue
   * @aliases dsii
   *
   * @usage drush dsii /data/issues/23 354 tif --issue-timestamp="1532704217" --issue-title="Atlantic Business Report"
   *   Add an issue.
   */
  public function importIssue(
    $source_directory,
    $parent_serial_id,
    $image_extension = 'tif',
    $options = [
      'issue-timestamp' => InputOption::VALUE_REQUIRED,
      'issue-title' => InputOption::VALUE_REQUIRED,
      'issue-volume' => InputOption::VALUE_REQUIRED,
      'issue-issue' => InputOption::VALUE_REQUIRED,
      'issue-edition' => InputOption::VALUE_REQUIRED,
      'issue-supplement-title' => InputOption::VALUE_REQUIRED,
      'issue-missing-pages' => InputOption::VALUE_REQUIRED,
      'issue-errata' => InputOption::VALUE_REQUIRED,
      'issue-source-media' => 'Print',
      'issue-language' => 'en',
      'existing-issue-id' => InputOption::VALUE_REQUIRED,
    ]
  ) {
    $this->sourceDirectory = $source_directory;
    $this->parentSerialId = $parent_serial_id;
    $this->imageExtension = $image_extension;
    $this->options = $options;

    $this->validateOptions();
    $this->validateImagesExist();

    $this->validateUser();
    $this->validateDrupalUser();

    $this->createIssue();
    $this->createBatch();

    // Process the batch that has been created.
    batch_set($this->batch);
    drush_backend_batch_process();
  }

  /**
   * Add a file to the filesystem.
   *
   * @param string $source
   *   The full path & filename of the source file.
   * @param string $destination
   *   The file storage destination. Defaults to public.
   *
   * @return bool
   *   Returns True if source file is found. False otherwise.
   */
  private function addFileToFileSystem($source, $destination = 'public') {
    $file_basename = basename($source);
    $file_destination = "$destination://$file_basename";
    if (file_exists($source)) {
      $file_uri = file_unmanaged_copy($source, $file_destination,
        FILE_EXISTS_REPLACE);
      $file = File::Create([
        'uri' => $file_uri,
      ]);
      $file->save();

      return $file;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Create the initial batch definition.
   */
  private function createBatch() {
    $this->batch = [
      'title' => t(
        'Importing @file_count files from @source_directory as serial issue.',
        [
          '@file_count' => count($this->files),
          '@source_directory' => $this->sourceDirectory,
        ]
      ),
      'init_message' => t('Starting import'),
      'progressive' => FALSE,
      'operations' => [],
    ];

    $this->loadPagesIntoBatch();
  }

  /**
   * Create the issue object, optionally skipping it if one is provided.
   */
  private function createIssue() {
    if (!empty($this->options['existing-issue-id'])) {
      $this->issue = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_issue')
        ->load($this->options['existing-issue-id']);

      if (empty($this->issue)) {
        $this->displayErrorQuit(
          t(
            'The existing issue of ID [@parent_issue_id] does not exist or is not a digital serial issue entity.',
            [
              '@parent_issue_id' => $this->parent_issue_id,
            ]
          )
        );
      }
    }
    else {
      // Create the issue.
      $this->issue = SerialIssue::create([
        'issue_title' => $this->options['issue-title'],
        'issue_vol' => $this->options['issue-volume'],
        'issue_issue' => $this->options['issue-issue'],
        'issue_edition' => $this->options['issue-edition'],
        'issue_date' => DrupalDateTime::createFromTimestamp($this->options['issue-timestamp'])->format('Y-m-d'),
        'issue_missingp' => $this->options['issue-missing-pages'],
        'issue_errata' => $this->options['issue-errata'],
        'issue_language' => $this->options['issue-language'],
        'issue_media' => $this->options['issue-source-media'],
        'parent_title' => $this->parentSerialTitle,
        'status' => TRUE,
      ]);
      $this->issue->save();
    }
  }

  /**
   * Display an error message and quit the command.
   */
  private function displayErrorQuit($message) {
    drush_set_error(
      $message
    );
    die();
  }

  /**
   * Load files from the source directory.
   */
  private function loadFiles() {
    $finder = new Finder();
    $finder->files()
      ->in($this->sourceDirectory)
      ->name('*.' . $this->imageExtension);

    foreach ($finder as $file) {
      $this->files[] = $file->getPathName();
    }
  }

  /**
   * Load pages into the batch.
   */
  private function loadPagesIntoBatch() {
    foreach ($this->files as $page_image_filepath) {
      $page_no = pathinfo($page_image_filepath, PATHINFO_FILENAME);
      $page_ocr_filepath = $this->replaceFileExtension($page_image_filepath, 'txt');
      $page_hocr_filepath = $this->replaceFileExtension($page_image_filepath, 'html');

      $this->batch['operations'][] = [
        [
          $this,
          'createPage',
        ],
        [
          $this->issue->id(),
          $page_no,
          $page_no,
          $page_image_filepath,
          $page_ocr_filepath,
          $page_hocr_filepath,
        ],
      ];
    }
  }

  /**
   * Validate that the drupal user for the command is not admin.
   */
  private function validateDrupalUser() {
    $user = User::load(\Drupal::currentUser()->id());
    if ($user->id() == 0) {
      $this->displayErrorQuit(
        t('This command must be run in Drush with the -u (UID) argument.')
      );
    }
  }

  /**
   * Validate that images exist for import.
   */
  private function validateImagesExist() {
    $this->loadFiles();
    if (empty($this->files)) {
      $this->displayErrorQuit(
        t(
          'No images with the extension @image_extension were found in @source_dir',
          [
            '@image_extension' => $this->imageExtension,
            '@source_dir' => $this->sourceDirectory,
          ]
        )
      );
    }
  }

  /**
   * Validate that parent digital title exists.
   */
  private function validateIssueTitle() {
    if (empty($this->parent_issue_id) && empty($this->options['issue-title'])) {
      $this->displayErrorQuit(
        t('An issue title (--issue-title) is required for new issues.')
      );
    }
  }

  /**
   * Validate that parent digital title exists.
   */
  private function validateIssueTimestamp() {
    if (empty($this->parent_issue_id) && empty($this->options['issue-timestamp'])) {
      $this->displayErrorQuit(
        t('An issue timestamp (--issue-timestamp) is required for new issues.')
      );
    }

    if (empty($this->parent_issue_id) && !$this->isValidTimeStamp($this->options['issue-timestamp'])) {
      $this->displayErrorQuit(
        t('The issue timestamp (--issue-timestamp) is invalid.')
      );
    }
  }

  /**
   * Validate the options passed.
   */
  private function validateOptions() {
    $this->validateSourceDirectoryExists();
    $this->validateParentDigitalTitle();
    $this->validateIssueTitle();
    $this->validateIssueTimestamp();
  }

  /**
   * Validate that parent digital title exists.
   */
  private function validateParentDigitalTitle() {
    $this->loadFiles();

    $this->parentSerialTitle = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_title')
      ->load($this->parentSerialId);

    if (empty($this->parentSerialTitle)) {
      $this->displayErrorQuit(
        t(
          'The parent title ID [@parent_title_id] does not exist or is not a digital serial title entity.',
          [
            '@parent_title_id' => $this->parentSerialId,
          ]
        )
      );
    }
  }

  /**
   * Validate that the source directory exists.
   */
  private function validateSourceDirectoryExists() {
    if (
      !file_exists($this->sourceDirectory) ||
      !is_dir($this->sourceDirectory)
    ) {
      $this->displayErrorQuit(
        t(
          'The source directory [@source_dir] is not accessible.',
          [
            '@source_dir' => $this->sourceDirectory,
          ]
        )
      );
    }
  }

  /**
   * Validate if the user running the drush command is not root.
   */
  private function validateUser() {
    $processUser = posix_getpwuid(posix_geteuid());
    if ($processUser['name'] == 'root') {
      $this->displayErrorQuit(
        t('This command should almost never be run as root.')
      );
    }
  }

  /**
   * Batch callback : create a digital serial page.
   *
   * @param string $parent_issue
   *   The ID of the digital serial issue to use as the parent.
   * @param string $page_no
   *   The page number to use for the page.
   * @param string $page_sort
   *   The sort value to use for the page.
   * @param string $page_image_filepath
   *   The filepath of the image to use as the page asset.
   * @param string $page_ocr_filepath
   *   The (optional) filepath of the OCR file to associate with the page.
   * @param string $page_hocr_filepath
   *   The (optional) filepath of the HOCR file to associate with the page.
   * @param array $context
   *   The batch context array.
   */
  public static function createPage($parent_issue, $page_no, $page_sort, $page_image_filepath, $page_ocr_filepath = NULL, $page_hocr_filepath = NULL, &$context) {
    // Image File.
    $image_file = self::addFileToFileSystem($page_image_filepath);

    // OCR file.
    if (file_exists($page_ocr_filepath)) {
      $ocr_contents = file_get_contents($page_ocr_filepath);
    }
    else {
      $ocr_contents = NULL;
    }

    // HOCR file.
    if (file_exists($page_hocr_filepath)) {
      $hocr_contents = file_get_contents($page_hocr_filepath);
    }
    else {
      $hocr_contents = NULL;
    }

    // Create the issue.
    $page = SerialPage::create([
      'page_no' => $page_no,
      'page_sort' => $page_sort,
      'page_image' => $image_file->id(),
      'page_ocr' => $page_ocr_filepath,
      'page_hocr' => $page_hocr_filepath,
      'parent_issue' => $parent_issue,
      'status' => TRUE,
    ]);

    $page->save();

    $context['message'] = t(
      '[Issue#@issue_id] Imported page @page_id.',
      [
        '@issue_id' => $parent_issue,
        '@page_id' => $page->id(),
      ]
    );
  }

  /**
   * Determine if a string is a valid unix timestamp.
   */
  public static function isValidTimeStamp($timestamp) {
    return ((string) (int) $timestamp === $timestamp)
      && ($timestamp <= PHP_INT_MAX)
      && ($timestamp >= ~PHP_INT_MAX);
  }

  /**
   * Replace a filepath extension with another.
   *
   * @param string $filename
   *   The filename to replace.
   * @param string $new_extension
   *   The new extention to use.
   *
   * @return string
   *   The filename with the new extension.
   */
  public static function replaceFileExtension($filename, $new_extension) {
    $info = pathinfo($filename);
    return $info['filename'] . '.' . $new_extension;
  }

}
