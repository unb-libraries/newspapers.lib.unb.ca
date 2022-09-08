<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerException;
use Dockworker\DziTilerTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerDeploymentCommands;

/**
 * Defines commands to generate DZI tiles for a deployed NBHP application.
 */
class NewspapersGenerateDziTilesCommands extends DockworkerDeploymentCommands {

  use DziTilerTrait;

  const DRUSH_GET_ISSUE_FILES_COMMAND = 'drush eval "_newspapers_core_list_issues_files([%s])"';
  const MESSAGE_DATA_VOLUME_DOES_NOT_EXIST = 'The path provided as the data volume, %s, was not found';
  const MESSAGE_DATA_VOLUME_NOT_NBHP_FILESYSTEM = 'The path provided as the data volume, %s, does not appear to be a Drupal filesystem';
  const MESSAGE_SOURCE_FILE_DOES_NOT_EXIST = 'One of the source files, %s, was not found in the data volume';
  const NBHP_PAGE_PATH_WITHIN_FILESYSTEM = 'files/serials/pages';

  /**
   * The local filepath of the NBHP persistent data volume.
   *
   * @var string
   */
  protected string $dataVolumePath;

  /**
   * The path to the issue pages.
   *
   * @var string
   */
  protected string $issuePageImagePath;

  /**
   * The deployed k8s NBHP pod ID to query.
   *
   * @var string
   */
  protected string $issuePodId;

  /**
   * The deployed k8s NBHP pod env to query.
   *
   * @var string
   */
  protected string $issuePodEnv;

  /**
   * The NBHP digital issue IDs to generate tiles for.
   *
   * @var string[]
   */
  protected array $issueSourceIds = [];

  /**
   * The local source files to generate the DZI tiles for.
   *
   * @var string[]
   */
  protected array $localSourceFiles = [];

  /**
   * The source files to generate the DZI tiles for.
   *
   * @var string[]
   */
  protected array $pageSourceFiles = [];

  /**
   * Should we generate the DZI tiles for images that have existing tiles?
   *
   * @var bool
   */
  protected bool $regenerateExistingTiles = FALSE;

  /**
   * The number of source files that have been skipped due to existing tiles.
   *
   * @var int
   */
  protected int $skippedFiles = 0;

  /**
   * Generates DZI tiles for an NBHP issue's pages.
   *
   * This command requires both kubectl access to the kubernetes cluster and
   * the NBHP data volume mounted to a local path, although it does not copy any
   * data over the kubectl interface.
   *
   * @param string $issue_id
   *   The issue ID to generate the DZI tiles for.
   * @param string $volume_path
   *   The local filepath of the NBHP persistent data volume.
   * @param string $env
   *   The environment to generate the DZI tiles in.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $regenerate-existing
   *   Regenerates DZI tiles even if existing tiles are detected.
   *
   * @command nbhp:dzi:generate:issue
   * @usage 3707 /mnt/newspapers.lib.unb.ca/ prod
   *
   * @throws \Exception
   *
   * @kubectl
   */
  public function generateIssueDziTiles(
    string $issue_id,
    string $volume_path,
    string $env,
    array $options = [
      'regenerate-existing' => FALSE,
    ]
  ) {
    $this->issueSourceIds[] = $issue_id;
    $this->dataVolumePath = $volume_path;
    $this->issuePodEnv = $env;
    $this->regenerateExistingTiles = $options['regenerate-existing'];
    $this->setupSources();
    if (!empty($this->localSourceFiles)) {
      $num_operations = count($this->localSourceFiles);
      $this->io()->title("Generating DZI tiles for $num_operations files");
      $this->generateDziFiles($this->localSourceFiles);
    }
    else {
      $message = 'No files to generate DZI tiles for!';
      if (!empty($this->skippedFiles)) {
        $message .= " ($this->skippedFiles existing tiled images ignored)";
      }
      $this->say($message);
    }
  }

  /**
   * Sets up the file and pod sources necessary to generate the DZI tiles.
   *
   * @throws \Exception
   * @throws \Dockworker\DockworkerException
   */
  private function setUpSources() {
    $this->checkDataVolumeExists();
    $this->setUpDataVolumeSource();
    $this->setUpSourcePod();
    $this->setUpSourceFiles();
    $this->setUpLocalFiles();
    $this->checkLocalSourceFilesExist();
  }

  /**
   * Checks that the data volume containing the persistent filesystem exists.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function checkDataVolumeExists() {
    if (!file_exists($this->dataVolumePath)) {
      throw new DockworkerException(
        sprintf(
          self::MESSAGE_DATA_VOLUME_DOES_NOT_EXIST,
          $this->dataVolumePath
        )
      );
    }
  }

  /**
   * Sets up the persistent data volume source path.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function setUpDataVolumeSource() {
    $this->issuePageImagePath = implode(
      '/',
      [
        $this->dataVolumePath,
        self::NBHP_PAGE_PATH_WITHIN_FILESYSTEM,
      ]
    );
    if (!file_exists($this->issuePageImagePath)) {
      throw new DockworkerException(
        sprintf(
          self::MESSAGE_DATA_VOLUME_NOT_NBHP_FILESYSTEM,
          $this->dataVolumePath
        )
      );
    }
  }

  /**
   * Sets up the source k8s pod to query for the issue's attached pages.
   *
   * @throws \Exception
   */
  private function setUpSourcePod() {
    $this->issuePodId = $this->k8sGetLatestPod(
      $this->issuePodEnv,
      'deployment',
      'Generate DZI',
      TRUE
    );
  }

  /**
   * Retrieves and sets up the source files from the remote k8s pod.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function setUpSourceFiles() {
    $this->pageSourceFiles = array_merge(
      $this->pageSourceFiles,
      $this->kubernetesPodExecCommand(
        $this->issuePodId,
        $this->issuePodEnv,
        sprintf(
          self::DRUSH_GET_ISSUE_FILES_COMMAND,
          implode(
            ',',
            $this->issueSourceIds
          )
        )
      )
    );
  }

  /**
   * Maps the source files to the local data volume path.
   */
  private function setUpLocalFiles() {
    foreach ($this->pageSourceFiles as $source_file) {
      $this->localSourceFiles[] = implode(
        '/',
        [
          $this->issuePageImagePath,
          basename($source_file),
        ]
      );
    }
  }

  /**
   * Checks if the remote listed files exist in the local source.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function checkLocalSourceFilesExist() {
    foreach ($this->localSourceFiles as $source_idx => $source_file) {
      $this->checkLocalSourceFileExists($source_idx);
      if (!$this->regenerateExistingTiles) {
        $this->checkRemoveLocalTilesExists($source_idx);
      }
    }
  }

  /**
   * Checks if a local source file exists on disk.
   *
   * @param string $source_idx
   *   The index of the path in the localSourceFiles array to check.
   *
   * @throws \Dockworker\DockworkerException
   */
  private function checkLocalSourceFileExists(string $source_idx) {
    if (!file_exists($this->localSourceFiles[$source_idx])) {
      throw new DockworkerException(
        sprintf(
          self::MESSAGE_SOURCE_FILE_DOES_NOT_EXIST,
          $this->localSourceFiles[$source_idx]
        )
      );
    }
  }

  /**
   * Checks if DZI tiles have already been generated for a local source file.
   *
   * @param string $source_idx
   *   The index of the path in the localSourceFiles array to check.
   */
  private function checkRemoveLocalTilesExists($source_idx) {
    if (
      file_exists(
        $this->getLocalDziFilePath(
          $this->localSourceFiles[$source_idx]
        )
      )
    ) {
      unset($this->localSourceFiles[$source_idx]);
      $this->skippedFiles++;
    }
  }

  /**
   * Generates the expected DZI file path from an image's path.
   *
   * @param string $file_path
   *   The path to determine the DZI file path for.
   *
   * @return string
   *   The expected DZI file path.
   */
  private static function getLocalDziFilePath(string $file_path) : string {
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    return str_replace(
      ".$file_extension",
      '.dzi',
      $file_path
    );
  }

}
