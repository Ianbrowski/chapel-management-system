<?php
require_once __DIR__ . '/../models/Bulletin.php';
require_once __DIR__ . '/../models/ParishInfo.php';
require_once __DIR__ . '/../models/Merchandise.php';
require_once __DIR__ . '/../models/Chapel.php';
require_once __DIR__ . '/../models/ServiceModel.php';

class HomeController {
    private $conn;
    public $bulletinM;
    public $parishInfoM;
    public $merchandiseM;
    public $chapelM;
    public $serviceM;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->bulletinM = new Bulletin($conn);
        $this->parishInfoM = new ParishInfo($conn);
        $this->merchandiseM = new Merchandise($conn);
        $this->chapelM = new Chapel($conn);
        $this->serviceM = new ServiceModel($conn);
    }

    public function loadViewData() {
        return [
            'bulletin_items' => $this->bulletinM->getActive(),
            'contact_info' => $this->parishInfoM->getContactInfo(),
            'mass_schedules' => $this->parishInfoM->getMassSchedules(),
            'history_intro' => $this->parishInfoM->getHistoryIntro(),
            'history_items' => $this->parishInfoM->getHistoryItems(),
            'leadership_intro' => $this->parishInfoM->getLeadershipIntro(),
            'leadership' => $this->parishInfoM->getLeadership(),
            'custom_merch' => $this->merchandiseM->getAllItems(),
            'chapels' => $this->chapelM->getAll(),
            'services_catalog' => $this->serviceM->getFullCatalog(true),
            'certificate_types' => $this->serviceM->getCertificateTypes()
        ];
    }
}
