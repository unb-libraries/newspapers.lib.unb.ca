<?php

namespace Drupal\Tests\digital_serial_issue\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the /serials-issue-search endpoint's month and day handling.
 *
 * @group digital_serial_issue
 */
class IssueSearchTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['digital_serial_issue'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Issue IDs keyed by a short label.
   *
   * @var int[]
   */
  protected $ids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
    $issues = [
      'may17' => ['1910-05-17', '42', '44'],
      'nov08' => ['1910-11-08', '44', '1'],
      'oct01' => ['1910-10-01', '42', '64'],
      'oct04' => ['1910-10-04', '42', '64'],
      'oct11' => ['1910-10-11', '42', '65'],
    ];
    foreach ($issues as $key => [$date, $vol, $issue]) {
      $entity = $storage->create([
        'parent_title' => 1,
        'issue_title' => 'Test',
        'issue_date' => $date,
        'issue_vol' => $vol,
        'issue_issue' => $issue,
        'status' => 1,
      ]);
      $entity->save();
      $this->ids[$key] = (int) $entity->id();
    }
    $this->drupalLogin($this->drupalCreateUser(['view published serial issue entities']));
  }

  /**
   * Requests the endpoint and returns the matching IDs, asserting a 200.
   */
  protected function search(string $args): array {
    $body = $this->drupalGet("serials-issue-search/1/$args");
    $this->assertSession()->statusCodeEquals(200);
    $json = json_decode($body, TRUE);
    $this->assertIsArray($json, "JSON body for $args");
    $this->assertSame(200, $json['status']);
    $this->assertSame('GET', $json['method']);
    $ids = array_map('intval', array_values($json['data']));
    sort($ids);
    return $ids;
  }

  /**
   * Tests every month and day form the endpoint accepts.
   */
  public function testMonthDayHandling() {
    // Unpadded and zero-padded month and day match the same issue.
    $this->assertSame([$this->ids['oct11']], $this->search('1910/10/11/42/65'));
    $this->assertSame([$this->ids['may17']], $this->search('1910/5/17/42/44'));
    $this->assertSame([$this->ids['may17']], $this->search('1910/05/17/42/44'));
    $this->assertSame([$this->ids['nov08']], $this->search('1910/11/8/44/1'));
    $this->assertSame([$this->ids['nov08']], $this->search('1910/11/08/44/1'));
    $this->assertSame([$this->ids['oct01']], $this->search('1910/10/01/42/64'));

    // An unspecified day matches the month; month and day, the year.
    $oct_64 = [$this->ids['oct01'], $this->ids['oct04']];
    sort($oct_64);
    $this->assertSame($oct_64, $this->search('1910/10/LULL/42/64'));
    $this->assertSame($oct_64, $this->search('1910/LULL/LULL/42/64'));
    $this->assertSame($oct_64, $this->search('LULL/LULL/LULL/42/64'));
    $this->assertSame([], $this->search('1906/LULL/LULL/LULL/LULL'));

    // Invalid or ambiguous dates return empty data, not an error.
    foreach ([
      '1910/13/01/42/64',
      '1910/00/01/42/64',
      '1910/02/30/42/64',
      '1910/ab/01/42/64',
      '1910/10/-1/42/64',
      '1910/LULL/04/42/64',
      'LULL/10/04/42/64',
    ] as $args) {
      $this->assertSame([], $this->search($args), $args);
    }
  }

}
