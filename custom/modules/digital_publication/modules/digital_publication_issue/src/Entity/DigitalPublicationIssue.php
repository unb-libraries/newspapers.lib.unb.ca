<?php

namespace Drupal\digital_publication_issue\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Digital publication issue entity.
 *
 * @ingroup digital_publication_issue
 *
 * @ContentEntityType(
 *   id = "digital_publication_issue",
 *   label = @Translation("Digital publication issue"),
 *   handlers = {
 *     "storage" = "Drupal\digital_publication_issue\DigitalPublicationIssueStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_publication_issue\DigitalPublicationIssueListBuilder",
 *     "views_data" = "Drupal\digital_publication_issue\Entity\DigitalPublicationIssueViewsData",
 *     "translation" = "Drupal\digital_publication_issue\DigitalPublicationIssueTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_publication_issue\Form\DigitalPublicationIssueForm",
 *       "add" = "Drupal\digital_publication_issue\Form\DigitalPublicationIssueForm",
 *       "edit" = "Drupal\digital_publication_issue\Form\DigitalPublicationIssueForm",
 *       "delete" = "Drupal\digital_publication_issue\Form\DigitalPublicationIssueDeleteForm",
 *     },
 *     "access" = "Drupal\digital_publication_issue\DigitalPublicationIssueAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_publication_issue\DigitalPublicationIssueHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_publication_issue",
 *   data_table = "digital_publication_issue_field_data",
 *   revision_table = "digital_publication_issue_revision",
 *   revision_data_table = "digital_publication_issue_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer digital publication issue entities",
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
 *     "canonical" = "/admin/structure/digital_publication_issue/{digital_publication_issue}",
 *     "add-form" = "/admin/structure/digital_publication_issue/add",
 *     "edit-form" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/edit",
 *     "delete-form" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/delete",
 *     "version-history" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/revisions",
 *     "revision" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/revisions/{digital_publication_issue_revision}/view",
 *     "revision_revert" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/revisions/{digital_publication_issue_revision}/revert",
 *     "revision_delete" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/revisions/{digital_publication_issue_revision}/delete",
 *     "translation_revert" = "/admin/structure/digital_publication_issue/{digital_publication_issue}/revisions/{digital_publication_issue_revision}/revert/{langcode}",
 *     "collection" = "/admin/structure/digital_publication_issue",
 *   },
 *   field_ui_base_route = "digital_publication_issue.settings"
 * )
 */
class DigitalPublicationIssue extends RevisionableContentEntityBase implements DigitalPublicationIssueInterface {

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

    // If no revision author has been set explicitly, make the digital_publication_issue owner the
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
      ->setDescription(t('The user ID of author of the Digital publication issue entity.'))
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
      ->setLabel(t('Issue Printed Name'))
      ->setDescription(t('The verbatim title of the issue, as printed in this issue.'))
      ->setRevisionable(TRUE)
      ->setSettings(
            [
              'default_value' => '',
              'max_length' => 255,
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

    // The title that this issue is part of.
    $fields['parent_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Digital issue title'))
      ->setDescription(t('The title that the issue is part of.'))
      ->setRequired(TRUE)
      ->setSettings(
        [
          'target_type' => 'digital_publication_title',
          'handler' => 'default',
        ]
      )
      ->setDisplayOptions(
        'view',
        [
          'label' => 'above',
          'type' => 'number',
          'weight' => -1,
        ]
      )
      ->setDisplayOptions(
        'form',
        [
          'type' => 'options_select',
          'weight' => -1,
        ]
      );

    // The issue date.
    $fields['issue_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Issue date'))
      ->setDescription(t('The date the issue was issued.'))
      ->setSetting('datetime_type', 'date')
      ->setRequired(true)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The volume of the issue.
    $fields['volume'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Volume'))
      ->setDescription(t('The issue volume.'))
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

    // The issue number of the issue.
    $fields['issue'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Issue'))
      ->setDescription(t('The issue number.'))
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

    // The edition of the issue.
    $fields['edition'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Edition'))
      ->setDescription(t('The issue edition.'))
      ->setSettings(
        [
          'default_value' => '',
          'max_length' => 128,
          'text_processing' => 0,
        ]
      )->setDisplayOptions(
        'form',
        [
          'type' => 'string_textfield',
          'weight' => -10,
        ]
      );

    // The title notes for the issue.
    $fields['title_notes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title Notes'))
      ->setDescription(t('Any Notes Relating to Title.'))
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

    // The missing pages notes for the issue.
    $fields['missing_pages'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Missing Pages'))
      ->setDescription(t('Notes regarding missing pages, if any.'))
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

    // The errata for the issue.
    $fields['other_errata'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Errata'))
      ->setDescription(t('Other errata of note related to the issue.'))
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

    // The title notes for the issue.
    $fields['language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language'))
      ->setDescription(t('ISO 639-2 Code of the issue language.'))
      ->setSettings(
        [
          'default_value' => 'eng',
          'max_length' => 3,
          'text_processing' => 0,
        ]
      )->setDisplayOptions(
        'form',
        [
          'type' => 'string_textfield',
          'weight' => -10,
        ]
      );

    // The title notes for the issue.
    $fields['source_media'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source Media'))
      ->setDescription(t('The source media of this issue.'))
      ->setSettings(
        [
          'default_value' => 'print',
          'max_length' => 32,
          'text_processing' => 0,
        ]
      )->setDisplayOptions(
        'form',
        [
          'type' => 'string_textfield',
          'weight' => -10,
        ]
      );

    // The title that this issue is part of.
    $fields['issue_pages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Issue Pages'))
      ->setDescription(t('The pages belonging to this issue.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSettings(
        [
          'target_type' => 'digital_publication_page',
          'handler' => 'default',
        ]
      );

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Digital publication issue is published.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
