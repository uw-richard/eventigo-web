<?php declare(strict_types=1);

namespace App\Modules\Front\Components\Subscription;

use App\Modules\Core\Components\AbstractBaseControl;
use App\Modules\Core\Components\Form\Form;
use App\Modules\Core\Model\UserModel;
use App\Modules\Front\Model\Exceptions\Subscription\EmailExistsException;
use Nette\Database\Table\IRow;

class Subscription extends AbstractBaseControl
{
    /**
     * @var callable[]
     */
    public $onEmailExists = [];

    /**
     * @var callable[]
     */
    public $onSuccess = [];

    /**
     * @var UserModel
     */
    private $userModel;

    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * @throws EmailExistsException
     */
    public function processForm(Form $form): void
    {
        $values = $form->getValues();
        $user = $this->subscribe($values->email);
        $this->onSuccess($user->email);
    }

    protected function createComponentForm(): Form
    {
        $form = new Form;
        $form->setTranslator($this->translator->domain('front.subscription.form'));

        $form->addText('email', null, null, 254)
            ->setAttribute('placeholder', 'email.placeholder')
            ->setAttribute('type', 'email')
            ->addCondition(Form::FILLED)
                ->addRule(Form::EMAIL, 'email.validation');

        $form->addSubmit('subscribe', 'subscribe.label');

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    /**
     * @throws EmailExistsException
     * @throws \PDOException
     */
    protected function subscribe(string $email): ?IRow
    {
        try {
            $user = $this->userModel->subscribe($email);

            if ($this->getReflection()->getName() === __CLASS__) {
                $this->onSuccess($user->email);
            } else {
                return $user;
            }
        } catch (EmailExistsException $e) {
            $this->onEmailExists($email);
        }
    }
}
