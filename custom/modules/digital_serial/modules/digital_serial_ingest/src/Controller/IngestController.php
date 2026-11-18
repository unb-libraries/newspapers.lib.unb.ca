<?php

namespace Drupal\digital_serial_ingest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\digital_serial_ingest\IngestNormalizer;
use Drupal\digital_serial_ingest\TitleIssueList;
use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Serves the read endpoints used by tools/nbnp-ingest.
 */
class IngestController extends ControllerBase {

  /**
   * Constructs an IngestController.
   */
  public function __construct(protected TitleIssueList $titleIssueList) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('digital_serial_ingest.title_issue_list'));
  }

  /**
   * Returns an issue and its pages, looked up by UUID.
   *
   * The tool chooses each issue's UUID before creating it, so this is how it
   * finds out whether a create it attempted actually happened.
   */
  public function issueByUuid(string $uuid): JsonResponse {
    $issues = $this->entityTypeManager()
      ->getStorage('digital_serial_issue')
      ->loadByProperties(['uuid' => $uuid]);
    return $this->issueResponse(reset($issues) ?: NULL);
  }

  /**
   * Returns an issue and its pages, looked up by ID.
   */
  public function issueById(int $issue_id): JsonResponse {
    return $this->issueResponse(
      $this->entityTypeManager()->getStorage('digital_serial_issue')->load($issue_id)
    );
  }

  /**
   * Returns a digital title, so the tool can show what it is about to change.
   */
  public function title(int $title_id): JsonResponse {
    $title = $this->entityTypeManager()->getStorage('digital_serial_title')->load($title_id);
    if ($title === NULL) {
      return $this->notFound("No digital title $title_id.");
    }
    return new JsonResponse((new IngestNormalizer())->title($title));
  }

  /**
   * Lists a title's issues, so the tool can match issues imported before it.
   */
  public function titleIssues(int $title_id): JsonResponse {
    if ($this->entityTypeManager()->getStorage('digital_serial_title')->load($title_id) === NULL) {
      return $this->notFound("No digital title $title_id.");
    }
    return new JsonResponse([
      'title_id' => $title_id,
      'issues' => $this->titleIssueList->forTitle($title_id),
    ]);
  }

  /**
   * Builds the response for an issue that may not exist.
   */
  protected function issueResponse(?SerialIssueInterface $issue): JsonResponse {
    if ($issue === NULL) {
      return $this->notFound('No such issue.');
    }
    // Every page, published or not: the tool decides what to upload from
    // this, and missing an unpublished page would make it upload a duplicate.
    $page_ids = $this->entityTypeManager()
      ->getStorage('digital_serial_page')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('parent_issue', $issue->id())
      ->execute();
    $pages = $this->entityTypeManager()->getStorage('digital_serial_page')->loadMultiple($page_ids);
    return new JsonResponse((new IngestNormalizer())->issue($issue, $pages));
  }

  /**
   * Builds a 404 response.
   */
  protected function notFound(string $message): JsonResponse {
    return new JsonResponse(['error' => $message], 404);
  }

}
