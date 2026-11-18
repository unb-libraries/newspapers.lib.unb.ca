<?php

namespace Drupal\Tests\digital_serial_page\Kernel;

use Drupal\digital_serial_page\PageImageChecksum;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests when and how a page's image checksum is computed.
 *
 * Pages are never saved: saving creates directories under the site's real
 * files directory and reindexes issues. Their presave step is called directly.
 */
#[Group('digital_serial_page')]
#[RunTestsInSeparateProcesses]
class PageImageChecksumTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'digital_serial',
    'digital_serial_issue',
    'digital_serial_page',
    'digital_serial_title',
    'field',
    'file',
    'image',
    'node',
    'options',
    'system',
    'text',
    'user',
  ];

  /**
   * The page storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pageStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->pageStorage = $this->container->get('entity_type.manager')->getStorage('digital_serial_page');
  }

  /**
   * Creates a file entity with the given contents.
   */
  protected function createImageFile(string $name, string $contents): File {
    file_put_contents("public://$name", $contents);
    $file = File::create(['uri' => "public://$name"]);
    $file->save();
    return $file;
  }

  /**
   * Creates a page that looks already saved, with itself as its original.
   */
  protected function createExistingPage(File $file, ?string $checksum) {
    $page = $this->pageStorage->create([
      'id' => 1,
      'page_no' => '1',
      'page_image' => ['target_id' => $file->id()],
      PageImageChecksum::FIELD => $checksum,
    ]);
    $page->enforceIsNew(FALSE);
    $page->setOriginal(clone $page);
    return $page;
  }

  /**
   * Tests that a new page gets the checksum of its image, whatever was sent.
   */
  public function testNewPage() {
    $file = $this->createImageFile('a.jpg', 'page one');
    $page = $this->pageStorage->create([
      'page_no' => '1',
      'page_image' => ['target_id' => $file->id()],
      PageImageChecksum::FIELD => str_repeat('0', 64),
    ]);
    $page->preSave($this->pageStorage);
    $this->assertSame(hash('sha256', 'page one'), $page->getImageSha256());
  }

  /**
   * Tests that saves leaving the image alone do not read it again.
   */
  public function testUnchangedImageKeepsChecksum() {
    $file = $this->createImageFile('a.jpg', 'page one');
    $page = $this->createExistingPage($file, 'stored');
    $page->setPageSort('0002');
    $this->assertFalse(PageImageChecksum::needsRefresh($page));
    PageImageChecksum::refresh($page);
    $this->assertSame('stored', $page->getImageSha256());

    // A legacy page without a checksum stays without one.
    $legacy = $this->createExistingPage($file, NULL);
    PageImageChecksum::refresh($legacy);
    $this->assertNull($legacy->getImageSha256());
  }

  /**
   * Tests that replacing the image recomputes the checksum.
   */
  public function testReplacedImage() {
    $page = $this->createExistingPage($this->createImageFile('a.jpg', 'page one'), hash('sha256', 'page one'));
    $page->set('page_image', ['target_id' => $this->createImageFile('b.jpg', 'page two')->id()]);
    PageImageChecksum::refresh($page);
    $this->assertSame(hash('sha256', 'page two'), $page->getImageSha256());
  }

  /**
   * Tests that a client cannot set the checksum without changing the image.
   */
  public function testClientValueIsReplaced() {
    $page = $this->createExistingPage($this->createImageFile('a.jpg', 'page one'), hash('sha256', 'page one'));
    $page->set(PageImageChecksum::FIELD, str_repeat('f', 64));
    PageImageChecksum::refresh($page);
    $this->assertSame(hash('sha256', 'page one'), $page->getImageSha256());
  }

  /**
   * Tests that an unreadable image leaves the checksum empty.
   */
  public function testUnreadableImage() {
    $file = $this->createImageFile('gone.jpg', 'page one');
    unlink('public://gone.jpg');
    $page = $this->pageStorage->create([
      'page_no' => '1',
      'page_image' => ['target_id' => $file->id()],
    ]);
    PageImageChecksum::refresh($page);
    $this->assertNull($page->getImageSha256());
  }

}
