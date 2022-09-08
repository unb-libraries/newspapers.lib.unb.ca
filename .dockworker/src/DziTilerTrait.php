<?php

namespace Dockworker;

use Dockworker\QueuedParallelExecTrait;
use Robo\Contract\CommandInterface;
use Robo\Task\Base\Tasks;

/**
 * Provides methods of tiling images into DZI tile sets.
 */
trait DziTilerTrait {

  use QueuedParallelExecTrait;
  use Tasks;

  /**
   * The docker image to use when tiling the image.
   *
   * @var string
   */
  private string $imagemagickImage = 'unblibraries/imagemagick:latest';

  /**
   * Generates DZI tiles for files.
   *
   * @param string[] $files
   *   The array of files to generate the tiles for.
   *
   * @throws \Exception
   */
  public function generateDziFiles(array $files) {
    shell_exec("docker pull $this->imagemagickImage");
    foreach ($files as $file_to_process) {
      $this->setAddCommandToQueue($this->getDziTileCommand($file_to_process));
    }
    $this->setRunProcessQueue('Generate DZI files');
  }

  /**
   * Generates the command stack used to generate DZI tiles for an image.
   *
   * @param string $file
   *   The file to parse.
   * @param int $step
   *   The zoom step to use.
   * @param int $target_gid
   *   The gid to assign the generated files.
   * @param int $target_uid
   *   The uid to assign the generated files.
   * @param int $tile_size
   *   The tile size to use.
   *
   * @return \Robo\Contract\CommandInterface
   *   The command.
   */
  private function getDziTileCommand(
    string $file,
    int $step = 200,
    int $target_gid = 102,
    int $target_uid = 102,
    int $tile_size = 256
  ) : CommandInterface {
    $dzi_file_path_info = pathinfo($file);
    $tmp_dir = sys_get_temp_dir() . "/nbhp_dzi/{$dzi_file_path_info['filename']}";

    return $this->taskExecStack()
      ->stopOnFail()
      ->exec("sudo rm -rf $tmp_dir")
      ->exec("mkdir -p $tmp_dir")
      ->exec("cp $file $tmp_dir")
      ->exec("docker run -v  $tmp_dir:/data --rm {$this->imagemagickImage} /app/magick-slicer.sh -- -e jpg -i /data/{$dzi_file_path_info['basename']} -o /data/{$dzi_file_path_info['filename']} --dzi -s $step -w $tile_size -h $tile_size")
      ->exec("sudo cp -r $tmp_dir/{$dzi_file_path_info['filename']}_files {$dzi_file_path_info['dirname']}/")
      ->exec("sudo chown $target_uid:$target_gid -R {$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
      ->exec("sudo cp $tmp_dir/{$dzi_file_path_info['filename']}.dzi {$dzi_file_path_info['dirname']}/")
      ->exec("sudo chown $target_uid:$target_gid {$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi")
      ->exec("sudo rm -rf $tmp_dir");
  }

}
