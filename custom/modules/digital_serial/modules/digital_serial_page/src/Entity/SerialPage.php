<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Serial page entity.
 *
 * @ingroup digital_serial_page
 *
 * @ContentEntityType(
 *   id = "digital_serial_page",
 *   label = @Translation("Serial page"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_page\SerialPageListBuilder",
 *     "views_data" = "Drupal\digital_serial_page\Entity\SerialPageViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_page\Form\SerialPageForm",
 *       "add" = "Drupal\digital_serial_page\Form\SerialPageForm",
 *       "edit" = "Drupal\digital_serial_page\Form\SerialPageForm",
 *       "delete" = "Drupal\digital_serial_page\Form\SerialPageDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_page\SerialPageAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_page\SerialPageHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_page",
 *   admin_permission = "administer serial page entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "page_no",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/digital_serial/digital_serial_page/{digital_serial_page}",
 *     "add-form" = "/digital_serial/digital_serial_page/add",
 *     "edit-form" = "/digital_serial/digital_serial_page/{digital_serial_page}/edit",
 *     "delete-form" = "/digital_serial/digital_serial_page/{digital_serial_page}/delete",
 *     "collection" = "/digital_serial/digital_serial_page",
 *   },
 *   field_ui_base_route = "digital_serial_page.settings"
 * )
 */
class SerialPage extends ContentEntityBase implements SerialPageInterface {

  const PAGE_FILESTORE_LINK = '/sites/default/files';
  const PAGE_FILESTORE_PATH = DRUPAL_ROOT . '/sites/default/files';

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getActivePagerNo() {
    $sorted_issue_page_ids = $this
      ->getParentIssue()
      ->getChildPageIds();
    $active_page_arr_key = array_search($this->id(), array_keys($sorted_issue_page_ids));

    return ($active_page_arr_key === FALSE) ? 0 : $active_page_arr_key + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageOcr() {
    return $this->get('page_ocr')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPageOcr($page_ocr) {
    $this->set('page_ocr', $page_ocr);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageHocr() {
    return $this->get('page_hocr')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPageHocr($page_hocr) {
    $this->set('page_hocr', $page_hocr);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageNo() {
    return $this->get('page_no')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPageNo($page_no) {
    $this->set('page_no', $page_no);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageSort() {
    return $this->get('page_sort')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPageSort($page_sort) {
    $this->set('page_sort', $page_sort);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageImage() {
    return $this->get('page_image')->get(0)->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentIssue() {
    return $this->get('parent_issue')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentIssueId() {
    return $this->get('parent_issue')->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getParentTitle() {
    $issue = $this->getParentIssue();
    return $issue->get('parent_title')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentTitleId() {
    $issue = $this->getParentIssue();
    return $issue->get('parent_title')->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setParentIssue(SerialIssueInterface $issue) {
    $this->set('parent_issue', $issue->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentIssueById($issue_id) {
    $this->set('parent_issue', $issue_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['page_no'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Page Number'))
      ->setDescription(t('Enter the page number.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['page_sort'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Page Sort'))
      ->setDescription(t('Enter the value to use for alphanumeric page sorting.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['page_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('Upload the digital image of the page.'))
      ->setRequired(TRUE)
      ->setSettings([
        'file_directory' => 'serials/pages',
        'alt_field_required' => FALSE,
        'file_extensions' => 'png jpg jpeg tiff tif',
      ])
      ->setDisplayOptions('form', [
        'type' => 'image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['page_ocr'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Page OCR'))
      ->setDescription(t('The OCR corresponding to the page.'))
      ->setSettings([
        'default_value' => '',
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_long',
        'text_processing' => 0,
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['page_hocr'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Page HOCR'))
      ->setDescription(t('The HOCR corresponding to the page.'))
      ->setSettings([
        'default_value' => '',
        'text_processing' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_long',
        'text_processing' => 0,
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['parent_issue'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Issue'))
      ->setDescription(t('Issue this page is a part of.'))
      ->setSettings(
        [
          'target_type' => 'digital_serial_issue',
          'handler' => 'default',
        ]
      );

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Serial page is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Implements getStyledImage() method.
   *
   * @param string $image_style
   *   The machine name of an image style.
   *
   * @return array
   *   The render array of a given image style.
   */
  public function getStyledImage($image_style) {
    $file = $this->getPageImage();
    $variables = [
      'style_name' => $image_style,
      'uri' => $file->getFileUri(),
    ];

    // The image.factory service will check if our image is valid.
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    if ($image->isValid()) {
      $variables['width'] = $image->getWidth();
      $variables['height'] = $image->getHeight();
    }
    else {
      $variables['width'] = $variables['height'] = NULL;
    }

    $render_array = [
      '#theme' => 'image_style',
      '#width' => $variables['width'],
      '#height' => $variables['height'],
      '#style_name' => $variables['style_name'],
      '#uri' => $variables['uri'],
    ];

    return $render_array;
  }

  /**
   * Implements getLinkedStyledImage() method.
   *
   * @param string $image_style
   *   The machine name of an image style.
   *
   * @return object
   *   The link object containing the image.
   */
  public function getLinkedStyledImage($image_style) {
    $image = $this->getStyledImage($image_style);
    $rendered_image = render($image);
    $image_markup = Markup::create($rendered_image);
    $file = $this->getPageImage();
    $uri = $file->getFileUri();
    $url = Url::fromUri(file_create_url($uri));

    return (Link::fromTextAndUrl($image_markup, $url));
  }

  /**
   * Gets the absolute paths for the page's image.
   *
   * @return string
   *   The absolute paths for the page's image
   */
  public function getAbsolutePageImagePath() {
    $page_image = $this->getPageImage();
    if (!empty($page_image)) {
      $uri = $page_image->getFileUri();
      $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
      return $stream_wrapper_manager->realpath();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $page_image = $this->getPageImage();
    if (!empty($page_image)) {
      $page_image->delete();
    }
    $this->deleteDziFiles();
    $this->deletePdfFile();
    parent::delete();
  }

  /**
   * {@inheritDoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->movePageImageToPermanentStorage(TRUE);
    // @TODO: This needs to be enabled post-2024 migration.
    // $this->deleteDziFiles();
    // $this->deletePdfFile();
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritDoc}
   */
  public function getPagePermImageStorageUri() {
    $issue = $this->getParentIssue();
    $file = $this->getPageImage();

    if (!empty($issue) && !empty($file)) {
      return $issue->getStorageUri() . '/' . $file->getFilename();
    }
    return '';
  }

    /**
   * {@inheritDoc}
   */
  public function getPagePermImageStoragePath() {
    $issue = $this->getParentIssue();
    $file = $this->getPageImage();

    if (!empty($issue) && !empty($file)) {
      return $issue->getStoragePath() . '/' . $file->getFilename();
    }
    return '';
  }


  /**
   * {@inheritDoc}
   */
  public function movePageImageToPermanentStorage($move_file = TRUE, $verbose = FALSE) {
    $file = $this->getPageImage();
    $perm_storage_uri = $this->getPagePermImageStorageUri();

    $file_uri = $file->getFileUri();
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($file_uri);
    $abs_file_path = $stream_wrapper_manager->realpath();
    // Do nothing if no URI or already in permanent storage.
    if (
      empty($perm_storage_uri) ||
      $file->getFileUri() == $perm_storage_uri ||
      $abs_file_path == false ||
      !file_exists($abs_file_path)
      ) {
      // Something might be wrong. Doing stuff might make it worse.
      if ($verbose) {
        $id = $this->id();
        if (empty($perm_storage_uri)) {
          \Drupal::logger('digital_serial_page')->notice("No permanent storage URI found for page $id");
        }
        if ($file->getFileUri() == $perm_storage_uri) {
          \Drupal::logger('digital_serial_page')->notice("Page $id already in permanent storage");
        }
        if ($abs_file_path == false) {
          \Drupal::logger('digital_serial_page')->notice("File path for page $id is invalid");
        }
        if (!file_exists($abs_file_path)) {
          \Drupal::logger('digital_serial_page')->notice("File $abs_file_path for page $id does not exist in the filesystem");
        }
      }
      return;
    }
    $file_system = \Drupal::service('file_system');
    $issue = $this->getParentIssue();
    $issue->createStoragePath();

    if ($move_file) {
      $file_system->move(
        $file_system->realpath($file->getFileUri()),
        $this->getPagePermImageStoragePath()
      );
    }

    $file->setFileUri($perm_storage_uri);
    $file->setPermanent();
    $file->save();
  }

  /**
   * Removes the DZI files from disk for a page entity.
   *
   * The recursive remove is potentially dangerous but is mitigated by string
   * checks.
   */
  public function deleteDziFiles() {
    $dzi_info = $this->getDziUri();
    if (!empty($dzi_info)) {
      $dzi_path = $dzi_info['file'];
      unlink($dzi_path);
      $dzi_dir = str_replace('.dzi', '_files', $dzi_path);
      if (is_dir($dzi_dir) && str_contains($dzi_dir, '_files')) {
        _newspapers_core_rmdir_recursive($dzi_dir);
      }
    }
  }

  /**
   * Removes the PDF from disk for a page entity.
   */
  public function deletePdfFile() {
    $pdf_info = $this->getPdfUri();
    if (!empty($pdf_info)) {
      $pdf_path = $pdf_info['file'];
      unlink($pdf_path);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getPdfUri() {
    $issue_id = $this->getParentIssueId();
    $title_id = $this->getParentTitleId();
    $file = $this->getPageImage();
    $pdf_filename = str_replace('.jpg', '.pdf', $file->getFilename());

    $pdf_uri_schemas = [
      "/serials/pages/$title_id/$issue_id/$pdf_filename",
      "/serials/pages/pdf/$issue_id/$pdf_filename",
    ];

    foreach ($pdf_uri_schemas as $pdf_uri_schema) {
      $abs_path = self::PAGE_FILESTORE_PATH . $pdf_uri_schema;
      if (file_exists($abs_path)) {
        return [
          'path' => self::PAGE_FILESTORE_LINK . $pdf_uri_schema,
          'file' => $abs_path,
        ];
      }
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getDziUri() {
    $issue_id = $this->getParentIssueId();
    $title_id = $this->getParentTitleId();
    $file = $this->getPageImage();
    $dzi_filename = str_replace('.jpg', '.dzi', $file->getFilename());
    $dzi_uri_schemas = [
      "/serials/pages/$title_id/$issue_id/$dzi_filename",
      "/serials/pages/$dzi_filename",
    ];

    foreach ($dzi_uri_schemas as $dzi_uri_schema) {
      $abs_path = self::PAGE_FILESTORE_PATH . $dzi_uri_schema;
      if (file_exists($abs_path)) {
        return [
          'path' => self::PAGE_FILESTORE_LINK . $dzi_uri_schema,
          'file' => $abs_path,
        ];
      }
    }
    return [];
  }

}
