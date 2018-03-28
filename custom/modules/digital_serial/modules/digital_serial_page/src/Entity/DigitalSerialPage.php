<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Digital serial page entity.
 *
 * @ingroup digital_serial_page
 *
 * @ContentEntityType(
 *   id = "digital_serial_page",
 *   label = @Translation("Digital serial page"),
 *   handlers = {
 *     "storage" = "Drupal\digital_serial_page\DigitalSerialPageStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_page\DigitalSerialPageListBuilder",
 *     "views_data" = "Drupal\digital_serial_page\Entity\DigitalSerialPageViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_page\Form\DigitalSerialPageForm",
 *       "add" = "Drupal\digital_serial_page\Form\DigitalSerialPageForm",
 *       "edit" = "Drupal\digital_serial_page\Form\DigitalSerialPageForm",
 *       "delete" = "Drupal\digital_serial_page\Form\DigitalSerialPageDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_page\DigitalSerialPageAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_page\DigitalSerialPageHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_page",
 *   revision_table = "digital_serial_page_revision",
 *   revision_data_table = "digital_serial_page_field_revision",
 *   admin_permission = "administer digital serial page entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/digital_serial_page/{digital_serial_page}",
 *     "add-form" = "/admin/structure/digital_serial_page/add",
 *     "edit-form" = "/admin/structure/digital_serial_page/{digital_serial_page}/edit",
 *     "delete-form" = "/admin/structure/digital_serial_page/{digital_serial_page}/delete",
 *     "version-history" = "/admin/structure/digital_serial_page/{digital_serial_page}/revisions",
 *     "revision" = "/admin/structure/digital_serial_page/{digital_serial_page}/revisions/{digital_serial_page_revision}/view",
 *     "revision_revert" = "/admin/structure/digital_serial_page/{digital_serial_page}/revisions/{digital_serial_page_revision}/revert",
 *     "revision_delete" = "/admin/structure/digital_serial_page/{digital_serial_page}/revisions/{digital_serial_page_revision}/delete",
 *     "collection" = "/admin/structure/digital_serial_page",
 *   },
 *   field_ui_base_route = "digital_serial_page.settings"
 * )
 */
class DigitalSerialPage extends RevisionableContentEntityBase implements DigitalSerialPageInterface {

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
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly, make the digital_serial_page owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Digital serial page entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The page number of the page.
    $fields['page_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Page Number'))
      ->setDescription(t('The printed number of the page.'))
      ->setSettings(
        [
          'default_value' => '',
          'max_length' => 64,
          'text_processing' => 0,
        ]
      )
      ->setDisplayOptions(
        'form',
        [
          'type' => 'string_textfield',
          'weight' => -10,
        ]
      );

    // The page image of the page.
    $fields['page_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Page Image'))
      ->setDescription(t('Images illustrating the project components.'))
      ->setRequired(TRUE)
      ->setSettings([
        'file_directory' => 'digital_serial_pages',
        'alt_field_required' => FALSE,
        'file_extensions' => 'tif, tiff',
      ]);

    // The page notes for the page.
    $fields['page_notes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Page Notes'))
      ->setDescription(t('Any Notes Relating to the Page.'))
      ->setSettings(
        [
          'default_value' => '',
          'max_length' => 512,
          'text_processing' => 0,
        ]
      )->setDisplayOptions(
        'form',
        [
          'type' => 'string_texarea',
          'weight' => -10,
        ]
      );

    // The textual content of the page.
    $fields['page_text'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Page Text'))
      ->setDescription(t('The texual content of the page, typically from OCR.'))
      ->setRequired(TRUE)
      ->setSettings([
        'file_directory' => 'digital_serial_text',
        'alt_field_required' => FALSE,
        'file_extensions' => 'txt',
      ]);

    // The HOCR of the page content.
    $fields['page_hocr'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Page HOCR'))
      ->setDescription(t('The HOCR content representing the page, typically from OCR.'))
      ->setRequired(TRUE)
      ->setSettings([
        'file_directory' => 'digital_serial_hocr',
        'alt_field_required' => FALSE,
        'file_extensions' => 'hocr',
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Digital serial page is published.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
