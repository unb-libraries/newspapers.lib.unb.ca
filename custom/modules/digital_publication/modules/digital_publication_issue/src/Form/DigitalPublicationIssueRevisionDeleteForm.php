<?php

namespace Drupal\digital_publication_issue\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Digital publication issue revision.
 *
 * @ingroup digital_publication_issue
 */
class DigitalPublicationIssueRevisionDeleteForm extends ConfirmFormBase {


  /**
   * The Digital publication issue revision.
   *
   * @var \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   */
  protected $revision;

  /**
   * The Digital publication issue storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $DigitalPublicationIssueStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new DigitalPublicationIssueRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityStorageInterface $entity_storage, Connection $connection) {
    $this->DigitalPublicationIssueStorage = $entity_storage;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $entity_manager->getStorage('digital_publication_issue'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'digital_publication_issue_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => format_date($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.digital_publication_issue.version_history', ['digital_publication_issue' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_publication_issue_revision = NULL) {
    $this->revision = $this->DigitalPublicationIssueStorage->loadRevision($digital_publication_issue_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->DigitalPublicationIssueStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Digital publication issue: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    drupal_set_message(t('Revision from %revision-date of Digital publication issue %title has been deleted.', ['%revision-date' => format_date($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.digital_publication_issue.canonical',
       ['digital_publication_issue' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {digital_publication_issue_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.digital_publication_issue.version_history',
         ['digital_publication_issue' => $this->revision->id()]
      );
    }
  }

}
