<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use Nette\Database\Connection;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use Nette\Http\Request;

use Nette;

final class TiketyPresenter extends BasePresenter
{
    private $ticketManager;
    private $userManager;
    private $komentarManager;
    private $produktyManager;
    private $ukolManager;
    private $tiketStavManager;

	private const
		STAV_VYTVOREN = 1,
		STAV_RESENI = 2,
		STAV_CEKA = 3,
		STAV_ZAMITNUTO = 4,
		STAV_VYRESEN = 5;

    public function __construct(
            Model\TicketManager $ticketManager,
            Model\UserManager $userManager,
            Model\KomentarManager $komentarManager,
            Model\ProduktyManager $produktyManager,
            Model\UkolManager $ukolManager,
			Model\TiketStavManager $tiketStavManager
        )
    {
        $this->ticketManager = $ticketManager;
        $this->userManager = $userManager;
        $this->komentarManager = $komentarManager;
        $this->produktyManager = $produktyManager;
        $this->ukolManager = $ukolManager;
        $this->tiketStavManager = $tiketStavManager;
    }
    
	public function renderDefault(string $filtr="all"): void
	{
        
        switch($filtr){
            case 'all':
                $this->template->tikety = $this->ticketManager->getAll();
                break;
            case 'my':
                if($this->user->isLoggedIn()){
                    $this->template->tikety = $this->ticketManager->getAllByIdUser($this->getUser()->id);
                } else {
                    $this->flashMessage("Pro zobrazení Vašich tiketů se prosím přihlašte", "warning");
                    $this->template->tikety = $this->ticketManager->getAll();
                }
                break;
            case 'solved':
                    $this->template->tikety = $this->ticketManager->getAllByIdStav(self::STAV_VYRESEN);
                break;
        }

        if(!isset($this->template->tikety))
            $this->template->tikety = array();

		$celkem = $this->ticketManager->getAll()->count();
		$hotovo = $this->ticketManager->getCountStav(self::STAV_VYRESEN) + $this->ticketManager->getCountStav(self::STAV_ZAMITNUTO);

        $this->template->pocetCelkem = $celkem;
        $this->template->pocetVyrizenych = $hotovo;
        $this->template->pocetNedokoncenych = $celkem - $hotovo;
		if ($celkem != 0)
			$this->template->pocetZbyvajicichProcent = ($celkem - $hotovo) / $celkem * 100;
		else
			$this->template->pocetZbyvajicichProcent = 0;
    }
    
    private $id_tiket;

    public function renderDetail(int $id): void
    {
        $this->id_tiket = $id;
       /*//kontrola jestli je user přihlášen
        //https://doc.nette.org/cs/3.0/quickstart/authentication
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }*/
        $this->ticketManager->incStats($id);
        $this->template->tiket = $this->ticketManager->getById($id)->fetch();
        //$this->template->produkt_nazev = $this->produktyManager->getProduktName($this->template->tiket->id_produkt)
            //->fetch()->nazev;
        $this->template->tiket_autor = $this->userManager->getInfo($this->template->tiket->id_uzivatel)
            ->fetch();
        $this->template->komentare = $this->komentarManager->getAllByTicket($id);
        $this->template->role_info = $this->userManager->getAllRolesColors();
        $this->template->ukoly = $this->ukolManager->getAllByTicket($id);
        $this->template->historie_tiketu = $this->ticketManager->getAllHistory($id);
    }

    //UI\Form vygeneruje formular ve tvaru tabulky pro VIEW
    protected function createComponentMakeTiketForm(): Form
    {
		// TODO: pouklízet komentáře
        //https://developer.snapappointments.com/bootstrap-select/examples/
        //https://doc.nette.org/cs/3.0/form-rendering toto je celkem dulezite
        //https://doc.nette.org/cs/3.0/forms
        $form = new Form;
        $form->addSelect('id_produkt', 'Produkt:', $this->produktyManager->getProduktyByName())
			//->setDefaultValue(1)
			->setRequired('Zadejte prosím produkt')
			->setAttribute('class', 'selectpicker')
			//->setAttribute('data-live-search-style', 'startsWith') nevim co to dělá :D
			->setAttribute('data-live-search', 'true')
			->setAttribute('data-size', '5')
			->setAttribute('data-width', '100%');
        $form->addText('predmet', 'Předmět:')
			->setRequired('Zadejte prosím předmět');
        $form->addTextArea('popis', 'Popis:')
			->setRequired('Zadejte prosím popis')
			->setAttribute('rows', '3')
			->addRule(Form::MIN_LENGTH, 'Prosím více popište Váš problém', 4)
			->addRule(Form::MAX_LENGTH, 'Popis je příliš dlouhý', 1000);
        $form->addHidden('id_uzivatel', (string) $this->getUser()->id); //TODO: dodelat proti padelaní
        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->onSuccess[] = [$this, 'makeTiketFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }

    // volá se po úspěšném odeslání formuláře
    public function makeTiketFormSucceeded(Form $form, \stdClass $values): void
    {
		// TODO: dokončit ten flash?
        // ...
        //$this->flashMessage('Byl jste úspěšně registrován.');
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('Pro vytvoření, nebo editování příspěvku se musíte přihlásit.');
            $this->redirect('Sign:in');
        }
        $this->ticketManager->create($values->id_produkt, (int) $values->id_uzivatel, $values->predmet, $values->popis);
        $this->redirect('Tikety:default');
    }

    /*protected function createComponentUploadForm()
    {

        $form = new Nette\Application\UI\Form;

        $form->addHidden('id',(string)$this->request->getParameter('id'));

        $form->addUpload('upload', 'Soubor')
            ->setRequired();
        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'uploadFormSucceeded'];

        return $form;
    }

    public function uploadFormSucceeded($form, $values)
    {

        if (($values->upload->size) > 0) { //kdyz je soubor skutecne poslan z formu

            $soubor = $values->upload;
            $soubor->move("prilohy" . $values->upload->name);

            $this->ticketManager->addPicture($values);

            $this->flashMessage('Soubor byl přidán', 'success');
            $this->redirect('this');

        } else {

            $this->flashMessage('Soubor nebyl přidán', 'warning');
            $this->redirect('this');

        }


    }*/


	// TODO: ? máme na to vůbec gui ?
	// nebo tohle je form, který vyskočí při signupu? (odpovědět na diskord)
    public function createComponentAddUserForm(): UI\Form
    {
        $form = new UI\Form;
        
        $form->addEmail('email', 'email')
            ->setRequired('Zadejte prosím email');
        $form->addText('jmeno', 'jmeno')
            ->setRequired('Zadejte prosím jmeno');
        $form->addText('prijmeni', 'prijmeni')
            ->setRequired('Zadejte prosím prijmeni');
        $form->addPassword('heslo', 'heslo')
            ->setRequired('Zadejte prosím heslo')
            ->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 8)
            ->addRule(Form::PATTERN, 'Musí obsahovat číslici', '.*[0-9].*');
        $form->addPassword('heslo_potvr', 'heslo znovu')
            ->setRequired('Zadejte heslo ještě jednou pro kontrolu.')
            ->addRule(Form::EQUAL,'Vaše vyplněné heslo se neshoduje.', $form['heslo']);;
        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->onSuccess[] = [$this, 'addUserFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }
    public function addUserFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('Pro vytvoření, nebo editování uživatele se musíte přihlásit.');
            $this->redirect('Sign:in');
        } else {
            $this->userManager->add($values->email, $values->heslo, $values->jmeno, $values->prijmeni);
            $this->redirect('Homepage:default');
        }
    }
    
    public function createComponentAddKomentar(): UI\Form
    {
        $form = new UI\Form;
        $form->addTextArea('komentar')
			->setRequired('Zadejte prosím popis')
			->setAttribute('rows', '3')
			->addRule(Form::MIN_LENGTH, 'Komentář je příliš krátký', 2)
			->addRule(Form::MAX_LENGTH, 'Komentář je příliš dlouhý', 1000);
        $form->addHidden('id_uzivatel', (string) $this->getUser()->id); //TODO: kontrolovat aby to neupravili
        $form->addHidden('id_tiket', (string) $this->id_tiket);
        
        $form->addSubmit('odeslat');
        $form->onSuccess[] = [$this, 'addKomentarFormSucceeded'];
        return $form;
    }
    public function addKomentarFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->komentarManager->add((int) $values->id_tiket, (int) $values->id_uzivatel, $values->komentar);
        $this->redirect('Tikety:detail?id='.(string) $values->id_tiket);
    }

	public function createComponentMakeUkolForm(): UI\Form
    {
        $form = new UI\Form;
        
        $result = $this->userManager->getAllWorkers();
        
        $workers[0] = 'Nevybrán';
        foreach($result as $worker)
        {
            $workers[$worker->id] = $worker->jmeno.' '.$worker->prijmeni;
        }

        $form->addHidden('id_upravujici', (string)$this->getUser()->id);
        $form->addHidden('id_tiket', (string) $this->id_tiket);
        
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
            ->addRule(Form::RANGE, 'Hodnota musí být v rozsahu 0-999999', [0, 999999])
            ->setAttribute('step', '1');

        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->onSuccess[] = [$this, 'actionAddUkolFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }
    public function actionAddUkolFormSucceeded (Nette\Application\UI\Form $form): void
    {
		$this->presenter->privileges("Manažer" , "Nejste opravněni k této akci.", "danger");
        $this->ukolManager->create($form);
        $this->flashMessage('Úkol byl přidán', 'info');
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

	public function createComponentSmazatTiketForm(): UI\Form
    {
        $form = new UI\Form;

		$id_tiket = (int)$this->request->getParameters()['id'];
        $form->addHidden('id_tiket', (string) $id_tiket);

        $form->addSubmit('potvrdit', 'Potvrdit');
        $form->onSuccess[] = [$this, 'actionSmazatTiketFormSucceeded'];
        $this->makeBootstrap4($form);
        return $form;
    }
    public function actionSmazatTiketFormSucceeded (Nette\Application\UI\Form $form): void
    {
		$this->presenter->privileges("Manažer" , "Nejste opravněni k této akci.", "danger");
		$this->ticketManager->delete($form->getValues()->id_tiket);
        $this->flashMessage('Tiket byl smazán', 'info');
        $this->redirect('Tikety:default');
    }
}
