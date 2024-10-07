<?php

namespace Drupal\digital_serial_title\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Digital Serial Title entity.
 *
 * @ingroup digital_serial_title
 *
 * @ContentEntityType(
 *   id = "digital_serial_title",
 *   label = @Translation("Digital Serial Title"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_title\SerialTitleListBuilder",
 *     "views_data" = "Drupal\digital_serial_title\Entity\SerialTitleViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "add" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "edit" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "delete" = "Drupal\digital_serial_title\Form\SerialTitleDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_title\SerialTitleAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_title\SerialTitleHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_title",
 *   admin_permission = "administer digital serial title entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   label_callback = "digital_serial_title_format_label",
 *   links = {
 *     "canonical" = "/serials/{digital_serial_title}",
 *     "add-form" = "/serials/add",
 *     "edit-form" = "/serials/{digital_serial_title}/edit",
 *     "delete-form" = "/serials/{digital_serial_title}/delete",
 *     "collection" = "/serials",
 *   },
 *   field_ui_base_route = "digital_serial_title.settings"
 * )
 */
class SerialTitle extends ContentEntityBase implements SerialTitleInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

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
  public function getParentPublication() {
    return $this->get('parent_title')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedDisplayTitle() {
    return $this->get('parent_title')->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Digital Serial Title entity.'))
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

    $fields['parent_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Title'))
      ->setSettings(
        [
          'target_type' => 'node',
          'handler' => 'default',
          'handler_settings' => ['target_bundles' => ['publication' => 'publication']],
        ]
      );

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Digital Serial Title is published.'))
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
   * {@inheritdoc}
   */
  public function delete() {
    $this->deleteChildPages();
    $this->deleteHoldingRecords();
    parent::delete();
  }

  /**
   * Delete the child issues that belong to this title.
   */
  private function deleteChildPages() {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $this->id());
    $issue_ids = $query->execute();

    foreach ($issue_ids as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      $issue->delete();
    }
  }

  /**
   * Delete the digital holdings records that belong to this title.
   */
  private function deleteHoldingRecords() {
    $query = \Drupal::entityQuery('serial_holding')
      ->condition('holding_digital_title', $this->id());
    $holding_ids = $query->execute();

    foreach ($holding_ids as $holding_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('serial_holding');
      $holding = $storage->load($holding_id);
      $holding->delete();
    }
  }

  /**
   * Get a holdings statement for this digital title.
   */
  public function getHoldingsStatement() {
    $digital_dates = $this->getHoldingDates();
    if (!empty($digital_dates) && count($digital_dates) > 1) {
      $reversed = array_reverse($digital_dates);
      $first_date = array_pop($reversed);
      $last_date = array_pop($digital_dates);
      $holdings_statement = $this->t('Issues between ') .
        $first_date->format('Y-m-d') .
        ' - ' .
        $last_date->format('Y-m-d');
      return $holdings_statement;
    }
    elseif (!empty($digital_dates) && count($digital_dates) == 1) {
      $only_date = array_pop($digital_dates);
      return $only_date->format('Y-m-d');
    }
    else {
      return "No holdings found";
    }
  }

  /**
   * Get a holdings dates for all issues.
   */
  public function getHoldingDates() {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $this->id());
    $issue_ids = $query->execute();

    $holdings_dates = [];
    foreach ($issue_ids as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      if (!empty($issue->get('issue_date')->date)) {
        $holdings_dates[] = $issue->get('issue_date')->date;
      }
    }
    sort($holdings_dates);

    return $holdings_dates;
  }

  /**
   * {@inheritdoc}
   */
  public function updateDigitalHoldingRecord() {
    $query = \Drupal::entityQuery('serial_holding')
      ->condition('holding_digital_title', $this->id());
    $holding_ids = $query->execute();

    foreach ($holding_ids as $holding_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('serial_holding');
      $holding = $storage->load($holding_id);
      $title_holding_dates = $this->getHoldingDates();

      if (!empty($title_holding_dates) && count($title_holding_dates) > 1) {
        $reversed = array_reverse($title_holding_dates);
        $start_date = array_pop($reversed);
        $end_date = array_pop($title_holding_dates);
        $holding->setHoldingStartDate($start_date);
        $holding->setHoldingEndDate($end_date);
        $holding->save();
      }
      elseif (!empty($title_holding_dates) && count($title_holding_dates) == 1) {
        $only_date = array_pop($title_holding_dates);
        $holding->setHoldingStartDate($only_date);
        $holding->setHoldingEndDate($only_date);
        $holding->save();
      }
    }
    drupal_flush_all_caches();
  }

  /**
   * {@inheritDoc}
   */
  public function getStorageUri() {
    $default_file_scheme = \Drupal::config('system.file')->get('default_scheme');
    $title_id = $this->id();
    return "$default_file_scheme://serials/pages/$title_id";
  }

  /**
   * {@inheritDoc}
   */
  public function getStoragePath() {
    $title_id = $this->id();
    return DRUPAL_ROOT . "/sites/default/files/serials/pages/$title_id";
  }

  /**
   * {@inheritDoc}
   */
  public function createStoragePath() {
    $title_absolute_path = $this->getStoragePath();
    if (!file_exists($title_absolute_path)) {
      mkdir($title_absolute_path, 0755, TRUE);
    }
    return $title_absolute_path;
  }

  /**
   * {@inheritDoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->createStoragePath();
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritDoc}
   */
  public function getIssues() {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $this->id());
    $issue_ids = $query->execute();

    $issues = [];
    foreach ($issue_ids as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      $issues[] = $issue;
    }

    return $issues;
  }

}
