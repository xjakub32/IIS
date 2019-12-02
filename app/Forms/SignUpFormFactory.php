<?php

declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;


final class SignUpFormFactory
{
	use Nette\SmartObject;

	private const PASSWORD_MIN_LENGTH = 8;

	/** @var FormFactory */
	private $factory;

	/** @var Model\UserManager */
	private $userManager;


	public function __construct(FormFactory $factory, Model\UserManager $userManager)
	{
		$this->factory = $factory;
		$this->userManager = $userManager;
	}


	public function create(callable $onSuccess): Form
	{
		$form = $this->factory->create();
		$form->addEmail('email', 'Váš e-mail:')
			->setRequired('Prosím zadejte Váš e-mail.');

		$form->addText('jmeno', 'jmeno')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte prosím jmeno');

        $form->addText('prijmeni', 'prijmeni')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
            setRequired('Zadejte prosím prijmeni');

		$form->addPassword('heslo', 'Zvolte heslo:')
			->setOption('description', sprintf('heslo musí obsahovat alespoň %d znaků', self::PASSWORD_MIN_LENGTH))
			->setRequired('Prosím zvolte heslo.')
			->addRule(Form::PATTERN, 'Musí obsahovat číslici', '.*[0-9].*')
			->addRule(Form::MIN_LENGTH, "Heslo musí mít alespoň %d znaků", self::PASSWORD_MIN_LENGTH);

		$form->addPassword('heslo_potvr', 'Potvrzení hesla')
			->setRequired('Zadejte heslo ještě jednou pro kontrolu.')
			->addRule(Form::EQUAL,'Vaše vyplněné heslo se neshoduje.', $form['heslo']);;
			
		$form->addSubmit('send', 'Registrovat');

		$form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess): void {
			try {
				$this->userManager->add($values->email, $values->heslo, $values->jmeno, $values->prijmeni);
			} catch (Model\DuplicateNameException $e) {
				$form['email']->addError('Uživatelské jméno již existuje');
				return;
			}
			$onSuccess();
		};

		return $form;
	}
}
