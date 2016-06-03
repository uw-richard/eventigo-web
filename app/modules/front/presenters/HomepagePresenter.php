<?php

namespace App\Modules\Front\Presenters;

use App\Modules\Core\Model\EventModel;
use App\Modules\Core\Model\TagModel;
use App\Modules\Core\Model\UserModel;
use App\Modules\Core\Model\UserTagModel;
use App\Modules\Core\Utils\Collection;
use App\Modules\Front\Components\EventsList\EventsListFactory;
use App\Modules\Front\Components\SubscriptionTags\ISubscriptionTagsFactory;
use Nette\Utils\DateTime;
use Nette\Utils\Html;


class HomepagePresenter extends BasePresenter
{
	/** @var EventModel @inject */
	public $eventModel;

	/** @var TagModel @inject */
	public $tagModel;

	/** @var ISubscriptionTagsFactory @inject */
	public $subscriptionTags;

	/** @var EventsListFactory @inject */
	public $eventsListFactory;

	/** @var \Kdyby\Facebook\Facebook @inject */
	public $facebook;

	/** @var UserTagModel @inject */
	public $userTagModel;


	/**
	 * @param string[] $tags
	 */
	public function renderDefault(array $tags)
	{
		if (!$tags) {
			$section = $this->getSession('subscriptionTags');
			$tags = $section->tags ?: $section->tags = [];
		}

		$this->template->eventModel = $this->eventModel;
		$this->template->tags = $this->tagModel->getAll();

		// Get array of all tags
		$allTags = [];
		foreach ($this->template->tags as $tag) {
			$allTags[] = $tag->code;
		}
		$this->template->allTags = $allTags;

		$this['subscriptionTags']['form']->setDefaults(['tags' => $tags]);
	}


	public function createComponentSubscriptionTags()
	{
		$control = $this->subscriptionTags->create();

		$control->onEmailExists[] = function ($email) {
			$this['eventsList']->redrawControl();

			$this->flashMessage(
				'<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>' .
				$this->translator->translate('front.subscription.message.emailExists',
					['email' => Html::el('strong')->setText($email)]),
				'success');

			$this->redrawControl('flash-messages');
		};

		$control->onSuccess[] = function ($email) {
			$this->getUser()->login(UserModel::SUBSCRIPTION_LOGIN, $email);

			$this->flashMessage(
				'<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>' .
				$this->translator->translate('front.subscription.message.success',
					['email' => Html::el('strong')->setText($email)]),
				'success');

			$this->redirect('Homepage:');
		};

		$control->onChange[] = function () {
			$this['eventsList']->redrawControl();
			$this->redrawControl('flash-messages');
		};

		return $control;
	}


	public function createComponentEventsList()
	{
		$section = $this->getSession('subscriptionTags');

		$tags = Collection::getNestedValues($section->tags);
		$tagsIds = $this->tagModel->getAll()->where('code', $tags)->fetchPairs(null, 'id');
		$events = $this->eventModel->getAllWithDates($tagsIds, new DateTime, null, $this->lastAccess);
		return $this->eventsListFactory->create($events);
	}


	public function handleFollowTag($tagCode)
	{
		$tag = $this->tagModel->getAll()->where(['code' => $tagCode]);
		$section = $this->presenter->getSession('subscriptionTags');

		// Follow tag
		if ($this->user->id) {
			$this->userTagModel->insert([
				'user_id' => $this->user->id,
				'tag_id' => $tag->id,
			]);
		} else {
			$section->tags[] = $tagCode;
		}

		// Refresh data
		$tagsIds = $this->tagModel->getAll()->where('code', $section->tags)->fetchPairs(null, 'id');
		$this->events = $this->eventModel->getAllWithDates($tagsIds, new DateTime);
		$this->followedTags = $section->tags;

		$this['eventsList']->redrawControl();
	}


	public function handleUnfollowTag($tagCode)
	{
		$tag = $this->tagModel->getAll()->where(['code' => $tagCode]);
		$section = $this->presenter->getSession('subscriptionTags');

		// Unfollow tag
		if ($this->user->id) {
			$this->userTagModel->delete([
				'user_id' => $this->user->id,
				'tag_id' => $tag->id,
			]);
		} else {
			if (($index = array_search($tagCode, $section->tags)) !== FALSE) {
				unset($section->tags[$index]);
			}
		}

		// Refresh data
		$tagsIds = $this->tagModel->getAll()->where('code', $section->tags)->fetchPairs(null, 'id');
		$this->events = $this->eventModel->getAllWithDates($tagsIds, new DateTime);
		$this->followedTags = $section->tags;

		$this['eventsList']->redrawControl();
	}


	/**
	 * @return \Kdyby\Facebook\Dialog\LoginDialog
	 */
	protected function createComponentFbLogin()
	{
		/** @var \Kdyby\Facebook\Dialog\LoginDialog $dialog */
		$dialog = $this->facebook->createDialog('login');

		$dialog->onResponse[] = function (\Kdyby\Facebook\Dialog\LoginDialog $dialog) {
			$fb = $dialog->getFacebook();

			if (!$fb->getUser()) {
				$this->flashMessage($this->translator->translate('front.homepage.fbLogin.failed'), 'danger');
				return;
			}

			try {
				$me = $fb->api('/me?fields=email,first_name,name');

				if (!$existing = $this->userModel->findByFacebookId($fb->getUser())) {
					$user = $this->userModel->signInViaFacebook($me);

					// TODO move to user tag service
					$section = $this->getSession('subscriptionTags');
					$chosenTags = Collection::getNestedValues($section->tags);
					$tags = $this->tagModel->getAll()->where('code IN (?)', $chosenTags)->fetchAll();
					foreach ($tags as $tag) {
						$this->userTagModel->insert([
							'tag_id' => $tag->id,
							'user_id' => $user->id,
						]);
					}
				}

				$existing = $this->userModel->updateFacebook($me, $fb->getAccessToken());

				$this->getUser()->login(new \Nette\Security\Identity($existing->id, null, $existing->toArray()));

			} catch (\Kdyby\Facebook\FacebookApiException $e) {
				\Tracy\Debugger::log($e, 'facebook');
				$this->flashMessage($this->translator->translate('front.homepage.fbLogin.failed'), 'danger');
			}

			$this->redirect('this');
		};

		return $dialog;
	}
}
