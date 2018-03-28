<?php

namespace Drupal\digital_serial_title\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Digital serial title entity.
 *
 * @ingroup digital_serial_title
 *
 * @ContentEntityType(
 *   id = "digital_serial_title",
 *   label = @Translation("Digital serial title"),
 *   handlers = {
 *     "storage" = "Drupal\digital_serial_title\DigitalSerialTitleStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_title\DigitalSerialTitleListBuilder",
 *     "views_data" = "Drupal\digital_serial_title\Entity\DigitalSerialTitleViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_title\Form\DigitalSerialTitleForm",
 *       "add" = "Drupal\digital_serial_title\Form\DigitalSerialTitleForm",
 *       "edit" = "Drupal\digital_serial_title\Form\DigitalSerialTitleForm",
 *       "delete" = "Drupal\digital_serial_title\Form\DigitalSerialTitleDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_title\DigitalSerialTitleAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_title\DigitalSerialTitleHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_title",
 *   revision_table = "digital_serial_title_revision",
 *   revision_data_table = "digital_serial_title_field_revision",
 *   admin_permission = "administer digital serial title entities",
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
 *     "canonical" = "/admin/structure/digital_serial_title/{digital_serial_title}",
 *     "add-form" = "/admin/structure/digital_serial_title/add",
 *     "edit-form" = "/admin/structure/digital_serial_title/{digital_serial_title}/edit",
 *     "delete-form" = "/admin/structure/digital_serial_title/{digital_serial_title}/delete",
 *     "version-history" = "/admin/structure/digital_serial_title/{digital_serial_title}/revisions",
 *     "revision" = "/admin/structure/digital_serial_title/{digital_serial_title}/revisions/{digital_serial_title_revision}/view",
 *     "revision_revert" = "/admin/structure/digital_serial_title/{digital_serial_title}/revisions/{digital_serial_title_revision}/revert",
 *     "revision_delete" = "/admin/structure/digital_serial_title/{digital_serial_title}/revisions/{digital_serial_title_revision}/delete",
 *     "collection" = "/admin/structure/digital_serial_title",
 *   },
 *   field_ui_base_route = "digital_serial_title.settings"
 * )
 */
class DigitalSerialTitle extends RevisionableContentEntityBase implements DigitalSerialTitleInterface {

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

    // If no revision author has been set explicitly, make the digital_serial_title owner the
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
      ->setDescription(t('The user ID of author of the Digital serial title entity.'))
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The serial title.'))
      ->setSettings([
        'max_length' => 50,
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
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Digital serial title is published.'))
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
