<?php

namespace Drupal\tweet_post\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tweet_post\TweeterCallService;

/**
 * Provides a 'TweetFeedBlock' block.
 *
 * @Block(
 *  id = "tweet_feed_block",
 *  admin_label = @Translation("Tweet feed block"),
 * )
 */
class TweetFeedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['test_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test Mode'),
      '#default_value' => !empty($this->configuration['test_mode']) ? $this->configuration['test_mode'] : '',
    ];

    $form['get_tweet_timelines'] = [
      '#type' => 'select',
      '#title' => $this->t('Get Tweet timelines'),
      '#description' => $this->t('Include a "destination" parameter in the link to return the user to the original view upon completing the contextual action.'),
      '#options' => [
        // https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=twitterapi&count=2
        'user_timeline' => $this->t('User timeline'),
        // https://api.twitter.com/1.1/statuses/home_timeline.json
        'home_timeline' => $this->t('Home timeline'),
        // https://api.twitter.com/1.1/statuses/mentions_timeline.json?count=2&since_id=14927799
        'mentions_timeline' => $this->t('Mentions timeline'),
      ],
      '#default_value' => !empty($this->configuration['get_tweet_timelines']) ? $this->configuration['get_tweet_timelines'] : '',
      '#weight' => 1,
      '#required' => TRUE,
    ];

    $form['screen_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Screen name'),
      '#default_value' => !empty($this->configuration['screen_name']) ? $this->configuration['screen_name'] : '',
      '#description' => $this->t('The screen name of the user for whom to return results.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => 2,
    ];

    $form['count'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Count'),
      '#default_value' => !empty($this->configuration['count']) ? $this->configuration['count'] : '',
      '#description' => $this->t('Specifies the number of Tweets to try and retrieve, up to a maximum of 200 per distinct request.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => 3,
    ];

    $form['since_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Since id'),
      '#default_value' => !empty($this->configuration['since_id']) ? $this->configuration['since_id'] : '',
      '#description' => $this->t('Returns results with an ID greater than (that is, more recent than) the specified ID.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => 4,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['since_id'] = $form_state->getValue('since_id');
    $this->configuration['count'] = $form_state->getValue('count');
    $this->configuration['screen_name'] = $form_state->getValue('screen_name');
    $this->configuration['get_tweet_timelines'] = $form_state->getValue('get_tweet_timelines');
    $this->configuration['test_mode'] = $form_state->getValue('test_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    switch ($this->configuration['get_tweet_timelines']) {
      case 'user_timeline':
        return $this->build_user_timeline($this->configuration['test_mode']);
        break;

      case 'home_timeline':
        return $this->build_home_timeline($this->configuration['test_mode']);
        break;

      case 'mentions_timeline':
        return $this->build_mention_timeline($this->configuration['test_mode']);
        break;

      default:
        return [
          '#theme' => 'tweet_feed_block',
          '#tweet' => [],
        ];
        break;
    }
  }

  /**
   * @param bool $mode
   *
   * @return array
   */
  private function build_user_timeline($mode = FALSE) {
    if ($mode || empty($this->configuration['screen_name'])) {
      $json = Json::decode(file_get_contents(__DIR__ . '/json/user_timeline.json'));
    }
    else {
      // ?screen_name=twitterapi&count=2
      $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
      $requestMethod = 'GET';
      $postfields = array(
        'screen_name' => $this->configuration['screen_name'],
        'count' => empty($this->configuration['count']) ? 3 : $this->configuration['count'],
      );
      /** @var TweeterCallService $twitter */
      $twitter = \Drupal::service('tweet_post.call_tweet');
      $conf = \Drupal::config('tweet_post.tweetconfig');
      // @todo: check for existing settings.
      $twitter->setSettings([
        'oauth_access_token' => $conf->get('oauth_access_token'),
        'oauth_access_token_secret' => $conf->get('oauth_access_token_secret'),
        'consumer_key' => $conf->get('consumer_key'),
        'consumer_secret' => $conf->get('consumer_secret'),
      ]);
      // @todo: here will be: try/catch.
      $json_responce = $twitter->buildOauth($url, $requestMethod)
        ->setPostfields($postfields)
        ->performRequest();
      $json = Json::decode($json_responce);
    }

    return [
      '#theme' => 'tweet_feed_user_timeline',
      '#tweet' => $json,
    ];
  }

  /**
   * @param bool $mode
   *
   * @return array
   */
  private function build_home_timeline($mode = FALSE) {
    $json = '';
    if ($mode) {
      $json = Json::decode(file_get_contents(__DIR__ . '/json/home_timeline.json'));
    }

    return [
      '#theme' => 'tweet_feed_home_timeline',
      '#tweet' => $json,
    ];
  }

  /**
   * @param bool $mode
   *
   * @return array
   */
  private function build_mention_timeline($mode = FALSE) {
    $json = '';
    if ($mode) {
      $json = Json::decode(file_get_contents(__DIR__ . '/json/mentions_timeline.json'));
    }

    return [
      '#theme' => 'tweet_feed_mentions_timeline',
      '#tweet' => $json,
    ];
  }

}
