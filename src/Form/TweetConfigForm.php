<?php

namespace Drupal\tweet_post\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TweetConfigForm.
 */
class TweetConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'tweet_post.tweetconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tweet_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tweet_post.tweetconfig');

    $form['oauth_access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OAuth Access Token'),
      '#description' => $this->t('your oauth access token.'),
      '#default_value' => $config->get('oauth_access_token'),
    ];
    $form['oauth_access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OAuth Access Token Secret'),
      '#description' => $this->t('your oauth access token secret.'),
      '#default_value' => $config->get('oauth_access_token_secret'),
    ];
    $form['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Key'),
      '#description' => $this->t('your consumer key.'),
      '#default_value' => $config->get('consumer_key'),
    ];
    $form['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Secret'),
      '#description' => $this->t('your consumer secret.'),
      '#default_value' => $config->get('consumer_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('tweet_post.tweetconfig')
      ->set('oauth_access_token', $form_state->getValue('oauth_access_token'))
      ->set('oauth_access_token_secret', $form_state->getValue('oauth_access_token_secret'))
      ->set('consumer_key', $form_state->getValue('consumer_key'))
      ->set('consumer_secret', $form_state->getValue('consumer_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
