<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use Nette\Database\Connection;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use Nette\Http\Request;

use Nette;

final class AdministracePresenter extends BasePresenter
{
    private $ticketManager;
    private $userManager;
    private $komentarManager;
    private $produktyManager;
    private $ukolManager;
    private $ukolStavManager;

	private const
		ROLE_ZAKAZNIK = 1,
		ROLE_PRACOVNIK = 2,
		ROLE_MANAZER = 3,
		ROLE_VEDOUCI = 4,
		ROLE_ADMIN = 5;

    public function __construct(
            Model\TicketManager $ticketManager,
            Model\UserManager $userManager,
            Model\KomentarManager $komentarManager,
            Model\ProduktyManager $produktyManager,
            Model\UkolManager $ukolManager,
            Model\UkolStavManager $ukolStavManager
        )
    {
        $this->ticketManager = $ticketManager;
        $this->userManager = $userManager;
        $this->komentarManager = $komentarManager;
        $this->produktyManager = $produktyManager;
        $this->ukolManager = $ukolManager;
        $this->ukolStavManager = $ukolStavManager;
    }
    
	private $id_uzivatel;
  
	public function renderDefault(int $id = 0): void
	{
        //kontrola oprávnění
        $this->presenter->privileges("Admin");

        $this->template->uzivatele = $this->userManager->getAll();
        $this->template->id_selected = $id;
        
        $this->id_uzivatel = $id;
        if($id != 0)
            $this->template->vybrany_uzivatel = $this->userManager->getInfo($id)->fetch();
    }

    public function renderProfil(): void
	{
        //kontrola jestli je user přihlášen (https://doc.nette.org/cs/3.0/quickstart/authentication)
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
        $this->template->uzivatel =  $this->userManager->getInfo($this->getUser()->id)->fetch();

    }

	public function createComponentAddUserForm(): UI\Form
	{
		$form = new UI\Form;

		$form->addEmail('email', 'email')
			->setRequired('Zadejte prosím email');
		$form->addText('jmeno', 'jmeno')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte prosím jmeno');
		$form->addText('prijmeni', 'prijmeni')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte prosím prijmeni');
		$form->addPassword('heslo', 'heslo')
			->setRequired('Zadejte prosím heslo')
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 8)
			->addRule(Form::PATTERN, 'Musí obsahovat číslici', '.*[0-9].*');
		$form->addPassword('heslo_potvr', 'heslo znovu')
			->setRequired('Zadejte heslo ještě jednou pro kontrolu.')
			->addRule(Form::EQUAL,'Vaše vyplněné heslo se neshoduje.', $form['heslo']);
		$form->addSelect('role', 'Role');
		$form->addSubmit('vytvorit', 'Vytvořit');
		$form->onSuccess[] = [$this, 'actionAddUser'];
		$this->makeBootstrap4($form);
		return $form;
	}
	public function actionAddUser(UI\Form $form): void
	{
        $this->presenter->privileges("Admin" , "Nejste opravněni k této akci.", "danger");

		$form = $form->getValues();
		$this->userManager->add($form->email, $form->heslo, Strings::firstUpper(Strings::lower($form->jmeno)), Strings::firstUpper(Strings::lower($form->prijmeni)), $form->role);
		$this->redirect('Administrace:default');
	}

	public function createComponentAddReseni(): UI\Form
    {
        $form = new UI\Form;
        
		$form->addHidden('id_upravujici', (string)$this->getUser()->id);
		$form->addHidden('id_ukolu', (string)$this->request->getParameter('id'));
		
		$form->addTextArea('reseni', 'Řešení úkolu')
			->setRequired('Zadejte řešení úkolu');
		
		$form->addText('cas', 'Strávený čas')
			->setDefaultValue(0)
			->setRequired('Zadejte strávený čas')
			->addRule(Form::FLOAT, 'Strávený čas musí být zadán jako číslo (v hodinách)')
			->setAttribute('step', '1')
			->addRule(Form::RANGE, 'Rozumný rozsah hodnot prosím', [0, 9999]);
		
		if((int)$this->request->getParameter('id') != NULL)
			$form['reseni']->setDefaultValue($this->ukolManager->getInfo((int)$this->request->getParameter('id'))->fetch()->popis_reseni);	

        $form->addSubmit('upravit', 'Upravit');
        $form->onSuccess[] = [$this, 'actionAddReseni'];
        $this->makeBootstrap4($form);
        return $form;
	}
	public function actionAddReseni(UI\Form $form): void
	{
        $this->presenter->privileges("Pracovník" , "Nejste opravněni k této akci.", "danger");
		$id_ukolu = $form->getValues()->id_ukolu;
		$this->ukolManager->editReseni($form);
		$this->ukolManager->changeStav((int)$id_ukolu, 2);
		$this->flashMessage('Řešení pro úkol '.$id_ukolu.' bylo přídáno', 'info');
		$this->redirect('Administrace:ukoly', $id_ukolu);
	}

	public function createComponentUkolChangeState(): UI\Form
    {
        $form = new UI\Form;

		$id_ukolu = (int)$this->request->getParameter('id');
		$form->addHidden('id_ukolu', (string)$id_ukolu);

		if ($id_ukolu != null)
		{
			$result = $this->ukolStavManager->getAll();
			foreach($result as $stav)
			{
				$stavy[$stav->id] = $stav->stav;
			}

			$id_aktualniho_stavu = $this->ukolManager->getInfo($id_ukolu)->fetch()->stav;

			$form->addSelect('stav', 'Nový stav', $stavy)
				//->setDefaultValue($id_ukolu)
				->setAttribute('class', 'selectpicker')
				->setAttribute('data-live-search', 'true')
				->setAttribute('data-size', '5')
				->setAttribute('data-width', '100%');

			$form->addSubmit('zmenit', 'Změnit');
		}
        $form->onSuccess[] = [$this, 'actionUkolChangeState'];
        $this->makeBootstrap4($form);
        return $form;
	}
	public function actionUkolChangeState(UI\Form $form): void
	{
        $this->presenter->privileges("Manažer" , "Nejste opravněni k této akci.", "danger");
		$this->ukolManager->changeState($form);
		$this->redirect('Administrace:ukoly');
	}
	public function createComponentZmenitStavTiketuForm(): UI\Form
    {
        $form = new UI\Form;
        
        $result = $this->tiketStavManager->getAll();
        foreach($result as $stav)
        {
            $stavy[$stav->id] = $stav->stav;
        }

		$id_tiket = (int)$this->request->getParameters()['id'];
		$id_aktualniho_stavu = $this->ticketManager->getById($id_tiket)->fetch()->stav;

        $form->addHidden('id_tiket', (string) $id_tiket);

        $form->addSelect('stav', 'Nový stav', $stavy)
            ->setDefaultValue($id_aktualniho_stavu)
            ->setAttribute('class', 'selectpicker')
            ->setAttribute('data-live-search', 'true')
            ->setAttribute('data-size', '5')
            ->setAttribute('data-width', '100%');

        $form->addSubmit('zmenit', 'Změnit');
        $form->onSuccess[] = [$this, 'actionZmenitStavTiketuFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }
    public function actionZmenitStavTiketuFormSucceeded (Nette\Application\UI\Form $form): void
    {
		$this->presenter->privileges("Manažer" , "Nejste opravněni k této akci.", "danger");
		$this->ticketManager->zmenitStav($form);
        $this->flashMessage('Stav úkolu byl změněn', 'info');
    }


	public function createComponentChangeUserPrivilegesForm(): UI\Form
	{
		$form = new UI\Form;

		$targetUserId = $this->request->getParameter('id');

		$form->addHidden('id_uzivatele', $targetUserId);
		$form->addSelect('role', 'Role', [
			self::ROLE_ZAKAZNIK => 'Zákazník',
			self::ROLE_PRACOVNIK => 'Pracovník',
			self::ROLE_MANAZER => 'Manažer',
			self::ROLE_VEDOUCI => 'Vedoucí',
			self::ROLE_ADMIN => 'Admin'
		]);

		$userInfo = $this->userManager->getInfo($targetUserId)->fetch();
		if ($userInfo != null)
			$form['role']->setDefaultValue($userInfo->role);
		else
			$form['role']->setDefaultValue(self::ROLE_ZAKAZNIK);

		$form->addSubmit('zmenit', 'Změnit');
		$form->onSuccess[] = [$this, 'changeUserPrivilegesFormSucceeded'];
		$this->makeBootstrap4($form);
		return $form;
	}
	public function changeUserPrivilegesFormSucceeded(Nette\Application\UI\Form $form): void
	{
		$id_uzivatele = $form->getValues()->id_uzivatele;
		$this->userManager->changePrivilegesByRoleId($form);
		$this->flashMessage('Role uživatele byla změněna na '.$this->userManager->getRoleInfoById($form->getValues()->role)->fetch()->nazev.'', 'info');
		$this->redirect('Administrace:default', $id_uzivatele);
	}

	public function createComponentAssignProductToManagerForm(): UI\Form
	{
		$form = new UI\Form;

		$result = $this->userManager->getAllManagers(); // poznámka: jen manažeři, ne vedoucí ani admini
		$managers[0] = 'Nevybrán';
		foreach($result as $manager)
		{
			$managers[$manager->id] = $manager->id.' - '.$manager->jmeno.' '.$manager->prijmeni;
		}

		$produktId = $this->request->getParameter('id');
		$form->addHidden('id_produkt', $produktId);

		$form->addSelect('manager', 'Manažer', $managers);

		$userInfo = $this->produktyManager->getProduktById($produktId)->fetch();
		if ($userInfo != null && $userInfo->spravuje != null)
			$form['manager']->setDefaultValue($userInfo->spravuje);
		else
			$form['manager']->setDefaultValue(0);

		$form->addSubmit('priradit', 'Přiřadit');
		$form->onSuccess[] = [$this, 'assignProductToManagerFormSucceeded'];
		$this->makeBootstrap4($form);
		return $form;
	}
	public function assignProductToManagerFormSucceeded(Nette\Application\UI\Form $form): void
	{
		$this->produktyManager->assignManager($form);
		$this->flashMessage('Manažer byl přiřazen k produktu', 'info');
	}

	public function createComponentDeleteProdukt(): UI\Form
	{
		$form = new UI\Form;

		$form->addHidden('id_produkt', (string)$this->request->getParameter('id'));

		$form->addSubmit('smazat', 'Smazat')
			->setOption('description', 'Pozor: Jakmile smažete tento produkt, nebudete jej již schopni obnovit.');
		$form->onSuccess[] = [$this, 'actionDeleteProdukt'];
		$this->makeBootstrap4($form);
		return $form;
	}
    public function actionDeleteProdukt (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Vedoucí" , "Nejste opravněni k této akci.", "danger");
        $this->produktyManager->delete($form->getValues()->id_produkt);
        $this->flashMessage('Produkt byl smazán', 'info');
        $this->redirect('Administrace:produkty');
    }

    public function renderProdukty(int $id = 0): void 
    {
        //kontrola oprávnění
        $this->presenter->privileges("Manažer");

        //$this->template->produkty = $this->produktyManager->getAll()->fetchAll();
		$this->template->id_selected = $id;
        
        if ($id != 0)
			$this->template->vybrany_produkt = $this->produktyManager->getProduktById($id)->fetch();

		if(isset($this->template->vybrany_produkt)){

			if(!$this->getUser()->isInRole('Admin')){
				if(!$this->getUser()->isInRole('Vedoucí')){
					if(((int)$this->template->vybrany_produkt->spravuje != (int)$this->getUser()->id)){
						$this->flashMessage('Nemáte právo k zobrazení toho produkut', 'warning');
						unset($this->template->vybrany_produkt);
					}		
				}
			}
		}
		
		if($this->getUser()->isInRole('Manažer'))
			$this->template->produkty = $this->produktyManager->getAllByManager($this->getUser()->id, TRUE);
		elseif($this->getUser()->isInRole('Admin') || $this->getUser()->isInRole('Vedoucí'))
			$this->template->produkty = $this->produktyManager->getAll();
    }
    
    public function createComponentAddProdukt(): UI\Form
    {
        $form = new UI\Form;

        $nadprodukty = $this->produktyManager->getProduktyByName();
        $nadprodukty[0] = 'Není';
        
        $result = $this->userManager->getAllManagers();
        $managers[0] = 'Nevybrán';
        foreach($result as $manager)
        {
            $managers[$manager->id] = $manager->id.' - '.$manager->jmeno.' '.$manager->prijmeni;
        }
        
        $form->addText('nazev', 'Název')
            ->setRequired('Zadejte název produktu');

        $form->addSelect('spravuje', 'Spravuje', $managers)
            ->setDefaultValue(0)
            ->setAttribute('class', 'selectpicker')
            ->setAttribute('data-live-search', 'true')
            ->setAttribute('data-size', '5')
            ->setAttribute('data-width', '100%');

        $form->addSelect('nadprodukt', 'Nadprodukt', $nadprodukty)
            ->setRequired('Prosím vyberte nadprodukt')
            ->setDefaultValue(0)
            ->setAttribute('class', 'selectpicker')
            ->setAttribute('data-live-search', 'true')
            ->setAttribute('data-size', '5')
            ->setAttribute('data-width', '100%');

        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->onSuccess[] = [$this, 'addProduktFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }
	public function addProduktFormSucceeded(Nette\Application\UI\Form $form): void
	{
		$this->produktyManager->add($form);
		$this->flashMessage('Produkt byl přidán', 'info');
	}


    public function renderUkoly($id = NULL): void
    {
        //kontrola oprávnění
        $this->presenter->privileges("Pracovník" , "Nemáte opravnění pro přístup do této sekce.", "danger");

		$this->template->vybrany_ukol = $this->ukolManager->getInfo($id)->fetch();

		$this->template->id_selected = $id;
		if($this->getUser()->isInRole('Manažer'))
			$this->template->ukoly = $this->ukolManager->getAllByWorker($this->getUser()->id, TRUE);
		elseif($this->getUser()->isInRole('Pracovník'))
			$this->template->ukoly = $this->ukolManager->getAllByWorker($this->getUser()->id);
		elseif($this->getUser()->isInRole('Admin') || $this->getUser()->isInRole('Vedoucí'))
			$this->template->ukoly = $this->ukolManager->getAll();
    }

    public function createComponentAddUkol(): UI\Form
    {
        $form = new UI\Form;
        
        $result = $this->userManager->getAllWorkers();
        
        $workers[0] = 'Nevybrán';
        foreach($result as $worker)
        {
            $workers[$worker->id] = $worker->jmeno.' '.$worker->prijmeni;
        }

        $form->addHidden('id_upravujici', (string)$this->getUser()->id);
        
        $form->addTextArea('zadani', 'Zadání')
            ->setRequired('Zadejte znění úkolu');

        $form->addSelect('pracovnik', 'Pracovník', $workers)
            ->setDefaultValue(0)
            ->setAttribute('class', 'selectpicker')
            ->setAttribute('data-live-search', 'true')
            ->setAttribute('data-size', '5')
            ->setAttribute('data-width', '100%');

        $form->addText('predpoklad', 'Časová náročnost')
            ->setDefaultValue(0)
            ->setRequired('Zadejte časovou náročnost úkolu v hodinách')
            ->addRule(Form::FLOAT, 'Časová náročnost musí být zadána jako číslo (v hodinách)')
			->addRule(Form::RANGE, 'Rozumný rozsah hodnot prosím', [0, 9999])
            ->setAttribute('step', '1');

        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->onSuccess[] = [$this, 'actionAddUkol'];
        $this->makeBootstrap4($form);
        return $form;
    }
    public function actionAddUkol (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Manažer" , "Nemáte opravnění pro přístup do této sekce.", "danger");
        $this->ukolManager->create($form);
        $this->flashMessage('Úkol byl přidán', 'info');
        $this->redirect('Administrace:ukoly');
    }

	public function createComponentChangeThisUserDataForm(): UI\Form
	{
		$form = new UI\Form;

		$userId = $this->getUser()->id;
		$userInfo = $this->userManager->getInfo($userId)->fetch();

        $form->addHidden('id_uzivatele', (string)$userId);

		$form->addText('jmeno', 'Jméno')->
			setDefaultValue($userInfo->jmeno)->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte nové jméno prosím');
		$form->addText('prijmeni', 'Příjmení')->
			setDefaultValue($userInfo->prijmeni)->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte nové příjmení prosím');

		$form->addSubmit('zmenit', 'Změnit')
			->setOption('description', 'Změna údajů se projeví až po odhlášení.');
		$form->onSuccess[] = [$this, 'actionChangeUserData'];
		$this->makeBootstrap4($form);
		return $form;
	}
	public function createComponentChangeOtherUserDataForm(): UI\Form
	{
		$form = new UI\Form;

		$targetUserId = $this->request->getParameter('id');
		$form->addHidden('id_uzivatele', (string)$targetUserId);

		$form->addText('jmeno', 'Jméno')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte nové jméno prosím');
		$form->addText('prijmeni', 'Příjmení')->
			addRule(Form::PATTERN, 'Nesmí obsahovat číslice', '^[^\d]*$')->
			setRequired('Zadejte nové příjmení prosím');

		$userInfo = $this->userManager->getInfo($targetUserId)->fetch();
		if ($userInfo != null)
		{
			$form['jmeno']->setDefaultValue($userInfo->jmeno);
			$form['prijmeni']->setDefaultValue($userInfo->prijmeni);
		}

		$form->addSubmit('zmenit', 'Změnit');
		$form->onSuccess[] = [$this, 'actionChangeUserData'];
		$this->makeBootstrap4($form);
		return $form;
	}
    public function actionChangeUserData (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Zákazník" , "Nemáte opravnění pro přístup do této sekce.", "danger");
		$id_uzivatele = $form->getValues()->id_uzivatele;
        $this->userManager->changeUserData($form);
		$this->flashMessage('Údaje uživatele byly změněny', 'info');
		//$this->redirect('Administrace:default', $id_uzivatele); // NE toto NE
    }

	public function createComponentDeleteThisUserForm(): UI\Form
	{
		$form = new UI\Form;

        $form->addHidden('id_uzivatele', (string)$this->getUser()->id);

		$form->addSubmit('smazat', 'Smazat')
			->setOption('description', 'Pozor: Jakmile smažete svůj účet, nikdy jej již nebudete schopni obnovit.');
		$form->onSuccess[] = [$this, 'actionDeleteThisUser'];
		$this->makeBootstrap4($form);
		return $form;
	}
    public function actionDeleteThisUser (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Zákazník" , "Nemáte opravnění pro přístup do této sekce.", "danger");
        $this->userManager->delete($form->getValues()->id_uzivatele);
        $this->flashMessage('Váš účet byl smazán', 'info');
		$this->getUser()->logout();
        $this->redirect('Tikety:default');
    }

	public function createComponentDeleteOtherUserForm(): UI\Form
	{
		$form = new UI\Form;

		$form->addHidden('id_uzivatele', (string)$this->request->getParameter('id'));

		$form->addSubmit('smazat', 'Smazat')
			->setOption('description', 'Pozor: Jakmile smažete tento účet, nikdy jej již nebudete schopni obnovit.');
		$form->onSuccess[] = [$this, 'actionDeleteOtherUser'];
		$this->makeBootstrap4($form);
		return $form;
	}
    public function actionDeleteOtherUser (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Admin" , "Nemáte opravnění pro přístup do této sekce.", "danger");
        $this->userManager->delete($form->getValues()->id_uzivatele);
        $this->flashMessage('Uživatelský účet byl smazán', 'info');
	}
	
	public function createComponentChangeProdukt(): UI\Form
	{
		$form = new UI\Form;
		
		$id = $this->request->getParameter('id');
		$form->addHidden('id_produkt', (string)$id);
		
		if($id != NULL) {
			$produkt = $this->produktyManager->getProduktById((int)$id)->fetch();

			$nadprodukty = $this->produktyManager->getProduktyByName();
			$nadprodukty[0] = 'Není';

			$result = $this->userManager->getAllManagers();
			$managers[0] = 'Nevybrán';
			foreach($result as $manager)
			{
				$managers[$manager->id] = $manager->id.' - '.$manager->jmeno.' '.$manager->prijmeni;
			}

			$form->addText('nazev', 'Název')->
				setDefaultValue($produkt->nazev)->
				setRequired('Zadejte nový název prosím');

			/*
			$form->addSelect('spravuje', 'Spravuje', $managers)
				->setAttribute('class', 'selectpicker')
				->setAttribute('data-live-search', 'true')
				->setAttribute('data-size', '5')
				->setAttribute('data-width', '100%');
			if ($produkt->spravuje != null)
				$form['spravuje']->setDefaultValue($produkt->spravuje);
			else
				$form['spravuje']->setDefaultValue(0);
			*/

			$form->addSelect('nadprodukt', 'Nadprodukt', $nadprodukty)
				->setRequired('Prosím vyberte nadprodukt')
				->setDefaultValue(0)
				->setAttribute('class', 'selectpicker')
				->setAttribute('data-live-search', 'true')
				->setAttribute('data-size', '5')
				->setAttribute('data-width', '100%');
			if ($produkt->rodic != null)
				$form['nadprodukt']->setDefaultValue($produkt->rodic);
			else
				$form['nadprodukt']->setDefaultValue(0);

			$form->addSubmit('zmenit', 'Změnit');
		}

		$form->onSuccess[] = [$this, 'actionChangeProdukt'];
		$this->makeBootstrap4($form);
		return $form;
	}
    public function actionChangeProdukt (Nette\Application\UI\Form $form): void
    {
        $this->presenter->privileges("Vedoucí" , "Nemáte opravnění pro přístup do této sekce.", "danger");
        $this->produktyManager->change($form);
		$this->flashMessage('Produkt byl změněn', 'info');
		//$this->redirect('Administrace:produkty');
    }
}
