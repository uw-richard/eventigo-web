<?php

namespace App\Modules\Front\Components\Sign;

use App\Modules\Core\Components\BaseControl;
use App\Modules\Core\Components\Form\Form;
use App\Modules\Core\Model\UserModel;
use App\Modules\Email\Model\EmailService;
use Kdyby\Translation\Translator;
use Nette\Utils\ArrayHash;


class SignIn extends BaseControl
{
	/** @var array */
	public $onSuccess = [];

	/** @var array */
	public $onNonExists = [];

	/** @var UserModel */
	private $userModel;

	/** @var EmailService */
	private $emailService;


	public function __construct(Translator $translator, UserModel $userModel, EmailService $emailService)
	{
		parent::__construct($translator);
		$this->userModel = $userModel;
		$this->emailService = $emailService;
	}


	public function createComponentForm()
	{
		$form = new Form;
		$form->setTranslator($this->translator->domain('front.signIn.form'));

		$form->addText('email', 'email')
			->setAttribute('placeholder', 'email.placeholder')
			->addRule(Form::EMAIL, 'email.invalid')
			->setRequired('email.required');
		$form->addSubmit('submit', 'submit');

		$form->onSuccess[] = [$this, 'processForm'];
		return $form;
	}


	public function processForm(Form $form, ArrayHash $values)
	{
		if ($user = $this->userModel->getUserByEmail($values->email)) {
			$this->emailService->sendLogin($values->email, $user->token);
			$this->onSuccess($values->email);
		} else {
			$this->onNonExists($values->email);
		}
	}
}