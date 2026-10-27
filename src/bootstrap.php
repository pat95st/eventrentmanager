<?php

declare(strict_types=1);

// Zentrale Verdrahtung der Anwendung: Autoloader, DB-Verbindung, Repositories,
// Command-/Query-Bus-Registrierung und Routing. Bewusst ohne Framework/DI-
// Container gehalten, damit die CQRS/UseCase-Struktur unmittelbar sichtbar bleibt.

require_once __DIR__ . '/autoload.php';

// Translator.php definiert neben der Translator-Klasse auch die globale
// Helferfunktion t() (siehe dort) — der Klassen-Autoloader greift nur bei
// Klassenreferenzen, ein reiner Funktionsaufruf t() in einer View würde die
// Datei sonst nie laden. Analog zu e() in View.php, das aber bereits durch
// den Aufruf View::setViewsPath() weiter unten mitgeladen wird.
require_once __DIR__ . '/Infrastructure/Http/Translator.php';

// LiveSearchField.php enthält ausschließlich die globale Helferfunktion
// renderLiveSearchField() (keine Klasse) — aus demselben Grund wie bei
// Translator.php oben explizit requiret, da ein reiner Funktionsaufruf den
// Klassen-Autoloader nicht auslösen würde.
require_once __DIR__ . '/Infrastructure/Http/LiveSearchField.php';

use App\Application\Article\Command\AddArticleAccessoryHandler;
use App\Application\Article\Command\AddArticleImageHandler;
use App\Application\Article\Command\AddInventoryNumberHandler;
use App\Application\Article\Command\CreateArticleHandler;
use App\Application\Article\Command\DeleteArticleAccessoryHandler;
use App\Application\Article\Command\DeleteArticleHandler;
use App\Application\Article\Command\DeleteArticleImageHandler;
use App\Application\Article\Command\DeleteInventoryNumberHandler;
use App\Application\Article\Command\UpdateArticleAccessoryHandler;
use App\Application\Article\Command\UpdateArticleHandler;
use App\Application\Article\Command\UpdateInventoryNumberHandler;
use App\Application\Article\Query\GetArticleHandler;
use App\Application\Article\Query\GetInventoryNumberContextHandler;
use App\Application\Article\Query\ListArticlesHandler;
use App\Application\CaseItem\Command\AddCaseContentItemHandler;
use App\Application\CaseItem\Command\AddCaseInventoryNumberHandler;
use App\Application\CaseItem\Command\CreateCaseItemHandler;
use App\Application\CaseItem\Command\DeleteCaseContentItemHandler;
use App\Application\CaseItem\Command\DeleteCaseInventoryNumberHandler;
use App\Application\CaseItem\Command\DeleteCaseItemHandler;
use App\Application\CaseItem\Command\UpdateCaseContentItemHandler;
use App\Application\CaseItem\Command\UpdateCaseInventoryNumberHandler;
use App\Application\CaseItem\Command\UpdateCaseItemHandler;
use App\Application\CaseItem\Query\GetCaseItemHandler;
use App\Application\CaseItem\Query\ListCaseItemsHandler;
use App\Application\Category\Command\CreateCategoryHandler;
use App\Application\Category\Command\DeleteCategoryHandler;
use App\Application\Category\Command\UpdateCategoryHandler;
use App\Application\Category\Query\ListCategoriesHandler;
use App\Application\Command\CommandBus;
use App\Application\Customer\Command\CreateCustomerHandler;
use App\Application\Customer\Command\DeleteCustomerHandler;
use App\Application\Customer\Command\UpdateCustomerHandler;
use App\Application\Customer\Query\GetCustomerHandler;
use App\Application\Customer\Query\ListCustomersHandler;
use App\Application\LaborTime\Command\CreateLaborTimeArticleHandler;
use App\Application\LaborTime\Command\DeleteLaborTimeArticleHandler;
use App\Application\LaborTime\Command\UpdateLaborTimeArticleHandler;
use App\Application\LaborTime\Query\ListLaborTimeArticlesHandler;
use App\Application\Manufacturer\Command\CreateManufacturerHandler;
use App\Application\Manufacturer\Command\DeleteManufacturerHandler;
use App\Application\Manufacturer\Command\UpdateManufacturerHandler;
use App\Application\Manufacturer\Query\ListManufacturersHandler;
use App\Application\Printing\Command\CreateLabelPrinterHandler;
use App\Application\Printing\Command\DeleteLabelPrinterHandler;
use App\Application\Printing\Command\PrintInventoryNumberLabelHandler;
use App\Application\Printing\Command\SendTestPrintHandler;
use App\Application\Printing\Command\UpdateLabelPrinterHandler;
use App\Application\Printing\Query\ListLabelPrintersHandler;
use App\Application\Query\QueryBus;
use App\Application\Rental\Command\AddRentalItemHandler;
use App\Application\Rental\Command\ConfirmRentalItemAccessoriesHandler;
use App\Application\Rental\Command\CreateRentalHandler;
use App\Application\Rental\Command\DeleteRentalHandler;
use App\Application\Rental\Command\DeleteRentalItemHandler;
use App\Application\Rental\Command\EmailPackingListPdfHandler;
use App\Application\Rental\Command\IssueRentalItemHandler;
use App\Application\Rental\Command\ReturnRentalItemHandler;
use App\Application\Rental\Command\UndoReturnRentalItemHandler;
use App\Application\Rental\Command\UnissueRentalItemHandler;
use App\Application\Rental\Command\UpdateRentalHandler;
use App\Application\Rental\Command\UpdateRentalItemHandler;
use App\Application\Rental\Query\GetArticleAvailabilityHandler;
use App\Application\Rental\Query\GetRentalHandler;
use App\Application\Rental\Query\ListRentalHistoryForInventoryNumberHandler;
use App\Application\Rental\Query\ListRentalsForArticleHandler;
use App\Application\Rental\Query\ListRentalsForVenueHandler;
use App\Application\Rental\Query\ListRentalsHandler;
use App\Application\Rental\Query\ListRentalsInRangeHandler;
use App\Application\ElectricalInspection\Command\CreateElectricalInspectionHandler;
use App\Application\ElectricalInspection\Command\DeleteElectricalInspectionHandler;
use App\Application\ElectricalInspection\Command\UpdateElectricalInspectionHandler;
use App\Application\ElectricalInspection\Query\GetElectricalInspectionHandler;
use App\Application\ElectricalInspection\Query\ListElectricalInspectionsForInventoryNumberHandler;
use App\Application\Rma\Command\AttachRmaDocumentHandler;
use App\Application\Rma\Command\AttachRmaPackagePhotoHandler;
use App\Application\Rma\Command\CreateRmaHandler;
use App\Application\Rma\Command\DeleteRmaDocumentHandler;
use App\Application\Rma\Command\DeleteRmaPackagePhotoHandler;
use App\Application\Rma\Command\DeleteRmaHandler;
use App\Application\Rma\Command\UpdateRmaHandler;
use App\Application\Rma\Query\GetRmaHandler;
use App\Application\Rma\Query\ListRmasForInventoryNumberHandler;
use App\Application\Rma\Query\ListRmasHandler;
use App\Application\Supplier\Command\CreateSupplierHandler;
use App\Application\Supplier\Command\DeleteSupplierHandler;
use App\Application\Supplier\Command\UpdateSupplierHandler;
use App\Application\Supplier\Query\GetSupplierHandler;
use App\Application\Supplier\Query\ListSuppliersHandler;
use App\Application\Venue\Command\AddVenuePowerConnectionHandler;
use App\Application\Venue\Command\CreateVenueHandler;
use App\Application\Venue\Command\DeleteVenueHandler;
use App\Application\Venue\Command\DeleteVenuePowerConnectionHandler;
use App\Application\Venue\Command\UpdateVenueHandler;
use App\Application\Venue\Command\UpdateVenuePowerConnectionHandler;
use App\Application\Venue\Query\GetVenueHandler;
use App\Application\Venue\Query\GetVenuesNeedDistributorFlagsHandler;
use App\Application\Venue\Query\ListVenuePowerConnectionsHandler;
use App\Application\Venue\Query\ListVenuesHandler;
use App\Application\Warehouse\Command\CreateWarehouseHandler;
use App\Application\Warehouse\Command\DeleteWarehouseHandler;
use App\Application\Warehouse\Command\UpdateWarehouseHandler;
use App\Application\Rental\Command\UpdateRentalLogisticsHandler;
use App\Application\Warehouse\Query\GetWarehouseHandler;
use App\Application\Warehouse\Query\ListWarehousesHandler;
use App\Application\Auth\Command\AuthenticatePasswordHandler;
use App\Application\Auth\Command\BeginPasskeyLoginHandler;
use App\Application\Auth\Command\BeginPasskeyRegistrationHandler;
use App\Application\Auth\Command\CompletePasskeyLoginHandler;
use App\Application\Auth\Command\CompletePasskeyRegistrationHandler;
use App\Application\Auth\Command\CompletePasswordSetupHandler;
use App\Application\Auth\Command\DeletePasskeyHandler;
use App\Application\Auth\PasswordSetupTokenIssuer;
use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Auth\Query\ListPasskeysForUserHandler;
use App\Application\Auth\Command\RequestEmailOtpHandler;
use App\Application\Auth\Command\RequestPasswordSetupHandler;
use App\Application\Auth\Command\VerifyTwoFactorCodeHandler;
use App\Application\Settings\Command\DeleteCompanyBackgroundImageHandler;
use App\Application\Settings\Command\DeleteCompanyDarkLogoHandler;
use App\Application\Settings\Command\DeleteCompanyLogoHandler;
use App\Application\Settings\Command\SendTestMailHandler;
use App\Application\Settings\Command\UpdateCompanySettingsHandler;
use App\Application\Settings\Command\UpdateLegalSettingsHandler;
use App\Application\Settings\Command\UpdateOfferPdfSettingsHandler;
use App\Application\Settings\Command\UpdatePriceTiersHandler;
use App\Application\Settings\Command\UpdateSmtpSettingsHandler;
use App\Application\Settings\Command\UploadCompanyBackgroundImageHandler;
use App\Application\Settings\Command\UploadCompanyDarkLogoHandler;
use App\Application\Settings\Command\UploadCompanyLogoHandler;
use App\Application\Settings\Query\GetCompanySettingsHandler;
use App\Application\Settings\Query\GetLegalSettingsHandler;
use App\Application\Settings\Query\GetOfferPdfSettingsHandler;
use App\Application\Settings\Query\GetPriceTiersHandler;
use App\Application\Settings\Query\GetSmtpSettingsHandler;
use App\Application\User\Command\ChangeOwnPasswordHandler;
use App\Application\User\Command\ChangePasswordHandler;
use App\Application\User\Command\CreateUserHandler;
use App\Application\User\Command\DeleteUserHandler;
use App\Application\User\Command\DisableTwoFactorHandler;
use App\Application\User\Command\EnableEmailOtpHandler;
use App\Application\User\Command\EnableTotpHandler;
use App\Application\User\Command\UpdateDefaultPageSizeHandler;
use App\Application\User\Command\UpdateLocaleHandler;
use App\Application\User\Command\UpdateUserHandler;
use App\Application\User\Query\GetUserHandler;
use App\Application\User\Query\ListUsersHandler;
use App\Domain\User\Permission;
use App\Infrastructure\Config\FlatYamlConfig;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\AuthGuard;
use App\Infrastructure\Http\Controllers\ArticleController;
use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\CaseController;
use App\Infrastructure\Http\Controllers\CategoryController;
use App\Infrastructure\Http\Controllers\CustomerController;
use App\Infrastructure\Http\Controllers\DashboardController;
use App\Infrastructure\Http\Controllers\ElectricalInspectionController;
use App\Infrastructure\Http\Controllers\InventoryNumberController;
use App\Infrastructure\Http\Controllers\LabelPrinterController;
use App\Infrastructure\Http\Controllers\LaborTimeArticleController;
use App\Infrastructure\Http\Controllers\ManufacturerController;
use App\Infrastructure\Http\Controllers\CalendarController;
use App\Infrastructure\Http\Controllers\PasskeyController;
use App\Infrastructure\Http\Controllers\ProfileController;
use App\Infrastructure\Http\Controllers\RentalController;
use App\Infrastructure\Http\Controllers\OfferController;
use App\Infrastructure\Http\Controllers\OrderConfirmationController;
use App\Infrastructure\Http\Controllers\SetupPasswordController;
use App\Infrastructure\Csv\PriceListCsvExporter;
use App\Infrastructure\Pdf\PackingListPdfGenerator;
use App\Infrastructure\Pdf\PriceListPdfGenerator;
use App\Infrastructure\Pdf\OfferPdfGenerator;
use App\Infrastructure\Pdf\OrderConfirmationPdfGenerator;
use App\Infrastructure\Pdf\InvoicePdfGenerator;
use App\Infrastructure\Pdf\RichTextPdfRenderer;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Infrastructure\RichText\HtmlToRichTextParser;
use App\Infrastructure\EInvoice\XRechnungGenerator;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Infrastructure\Persistence\Pdo\MariadbDocumentNumberingSettingsRepository;
use App\Application\Invoice\Command\CreateInvoiceHandler;
use App\Application\Invoice\Command\UpdateInvoiceHandler;
use App\Application\Invoice\Command\DeleteInvoiceHandler;
use App\Application\Invoice\Command\MarkInvoiceSentHandler;
use App\Application\Invoice\Command\MarkInvoicePaidHandler;
use App\Application\Invoice\Command\MarkInvoiceCancelledHandler;
use App\Application\Invoice\Command\CreateInvoiceFromOfferHandler;
use App\Application\Invoice\Command\CreateInvoiceFromRentalHandler;
use App\Application\Invoice\Command\EmailInvoicePdfHandler;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Application\Invoice\Query\ListInvoicesHandler;
use App\Application\Settings\Command\UpdateInvoiceSettingsHandler;
use App\Application\Settings\Command\UpdatePricingSettingsHandler;
use App\Application\Settings\Command\UpdateInvoicePdfSettingsHandler;
use App\Application\Settings\Query\GetInvoiceSettingsHandler;
use App\Application\Settings\Query\GetPricingSettingsHandler;
use App\Application\Settings\Query\GetInvoicePdfSettingsHandler;
use App\Infrastructure\Http\Controllers\InvoiceController;
use App\Infrastructure\Http\Controllers\InvoiceSettingsController;
use App\Infrastructure\Http\Controllers\DocumentNumberingSettingsController;
use App\Infrastructure\Http\Controllers\PricingSettingsController;
use App\Infrastructure\Http\Controllers\InvoicePdfTemplateSettingsController;
use App\Infrastructure\Persistence\Pdo\MariadbInvoiceRepository;
use App\Application\BankImport\Command\CancelBankImportHandler;
use App\Application\BankImport\Command\ConfirmBankImportHandler;
use App\Application\BankImport\Command\ImportBankStatementHandler;
use App\Infrastructure\BankImport\BankImportStaging;
use App\Infrastructure\Http\Controllers\BankImportController;
use App\Infrastructure\Persistence\Pdo\MariadbInvoiceSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbPricingSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbInvoicePdfSettingsRepository;
use App\Application\Receipt\Command\CreateReceiptHandler;
use App\Application\Receipt\Command\UpdateReceiptHandler;
use App\Application\Receipt\Command\DeleteReceiptHandler;
use App\Application\Receipt\Command\MarkReceiptPaidHandler;
use App\Application\Receipt\Command\MarkReceiptOpenHandler;
use App\Application\Receipt\Query\ListReceiptsHandler;
use App\Application\Receipt\Query\GetReceiptHandler;
use App\Infrastructure\Http\Controllers\ReceiptController;
use App\Infrastructure\Persistence\Pdo\MariadbReceiptRepository;
use App\Application\Settings\Command\UpdateMailboxSettingsHandler;
use App\Application\Settings\Command\TestMailboxConnectionHandler;
use App\Application\Settings\Query\GetMailboxSettingsHandler;
use App\Application\ReceiptInbox\Command\AddAllowedSenderHandler;
use App\Application\ReceiptInbox\Command\DeleteAllowedSenderHandler;
use App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler;
use App\Application\ReceiptInbox\Command\ConvertReceiptInboxAttachmentToReceiptHandler;
use App\Application\ReceiptInbox\Query\ListAllowedSendersHandler;
use App\Application\ReceiptInbox\Query\ListReceiptInboxMessagesHandler;
use App\Application\User\Command\UpdateNotifyOnNewReceiptEmailHandler;
use App\Infrastructure\Http\Controllers\MailboxSettingsController;
use App\Infrastructure\Http\Controllers\ReceiptInboxController;
use App\Infrastructure\Http\ReceiptInboxBadge;
use App\Infrastructure\Persistence\Pdo\MariadbMailboxSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbReceiptInboxAllowedSenderRepository;
use App\Infrastructure\Persistence\Pdo\MariadbReceiptInboxMessageRepository;
use App\Infrastructure\Persistence\Pdo\MariadbReceiptInboxAttachmentRepository;
use App\Infrastructure\Persistence\Pdo\MariadbPushSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbPushSubscriptionRepository;
use App\Application\Settings\Command\EnsureVapidKeysGeneratedHandler;
use App\Application\Settings\Command\UpdatePushSettingsHandler;
use App\Application\Settings\Query\GetPushSettingsHandler;
use App\Application\Push\Command\SubscribeToPushHandler;
use App\Application\Push\Command\UnsubscribeFromPushHandler;
use App\Application\Push\Query\ListPushSubscriptionsForUserHandler;
use App\Infrastructure\Http\Controllers\PushSettingsController;
use App\Application\Offer\Command\CreateOfferHandler;
use App\Application\Offer\Command\UpdateOfferHandler;
use App\Application\Offer\Command\MarkOfferSentHandler;
use App\Application\Offer\Command\MarkOfferAcceptedHandler;
use App\Application\Offer\Command\MarkOfferDeclinedHandler;
use App\Application\Offer\Command\DeleteOfferHandler;
use App\Application\Offer\Command\ConvertOfferToRentalHandler;
use App\Application\Offer\Command\EmailOfferPdfHandler;
use App\Application\Offer\Query\GetOfferHandler;
use App\Application\Offer\Query\ListOffersHandler;
use App\Infrastructure\Http\Controllers\RmaController;
use App\Infrastructure\Http\Controllers\CompanySettingsController;
use App\Infrastructure\Http\Controllers\EmailTemplateSettingsController;
use App\Infrastructure\Http\Controllers\LegalPageController;
use App\Infrastructure\Http\Controllers\LegalSettingsController;
use App\Infrastructure\Http\Controllers\LocaleController;
use App\Infrastructure\Http\Controllers\OfferPdfTemplateSettingsController;
use App\Infrastructure\Http\Controllers\PriceTierSettingsController;
use App\Infrastructure\Http\Controllers\SmtpSettingsController;
use App\Infrastructure\Http\Controllers\SupplierController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Http\Controllers\VenueController;
use App\Infrastructure\Http\Controllers\VoucherController;
use App\Infrastructure\Http\Controllers\VoucherSettingsController;
use App\Infrastructure\Http\Controllers\WarehouseController;
use App\Infrastructure\Http\Locale;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Http\View;
use App\Infrastructure\Geocoding\NominatimGeocoder;
use App\Infrastructure\Mail\SmtpMailer;
use App\Infrastructure\Persistence\Pdo\Connection;
use App\Infrastructure\Persistence\Pdo\MariadbArticleAccessoryRepository;
use App\Infrastructure\Persistence\Pdo\MariadbArticleChannelListingRepository;
use App\Infrastructure\Persistence\Pdo\MariadbArticleRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCaseContentItemRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCaseInventoryNumberRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCaseItemRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCategoryRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCustomerRepository;
use App\Infrastructure\Persistence\Pdo\MariadbElectricalInspectionRepository;
use App\Infrastructure\Persistence\Pdo\MariadbInventoryNumberRepository;
use App\Infrastructure\Persistence\Pdo\MariadbLabelPrinterRepository;
use App\Infrastructure\Persistence\Pdo\MariadbLoginChallengeRepository;
use App\Infrastructure\Persistence\Pdo\MariadbLaborTimeArticleRepository;
use App\Infrastructure\Persistence\Pdo\MariadbManufacturerRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRentalIssuedItemRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRentalItemRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRentalRepository;
use App\Infrastructure\Persistence\Pdo\MariadbOfferRepository;
use App\Infrastructure\Persistence\Pdo\MariadbPasswordSetupTokenRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRmaDocumentRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRmaPackagePhotoRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRmaReceiptRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRmaRepository;
use App\Infrastructure\Persistence\Pdo\MariadbCompanySettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbEmailTemplateRepository;
use App\Infrastructure\Persistence\Pdo\MariadbGoogleBusinessSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbGoogleMerchantSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbIntegrationBaseSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbLegalSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbOfferPdfSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbPriceTierRepository;
use App\Infrastructure\Persistence\Pdo\MariadbSmtpSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbWhatsappBusinessSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbSupplierRepository;
use App\Infrastructure\Persistence\Pdo\MariadbUserRepository;
use App\Infrastructure\Persistence\Pdo\MariadbVenuePowerConnectionRepository;
use App\Infrastructure\Persistence\Pdo\MariadbVenueRepository;
use App\Infrastructure\Persistence\Pdo\MariadbWarehouseRepository;
use App\Infrastructure\Persistence\Pdo\MariadbUserSessionRepository;
use App\Infrastructure\Persistence\Pdo\MariadbWebauthnChallengeRepository;
use App\Infrastructure\Persistence\Pdo\MariadbWebauthnCredentialRepository;
use App\Infrastructure\Printing\LabelPrinterDriverRegistry;
use App\Infrastructure\Printing\TcpPrinterTransport;
use App\Infrastructure\WebAuthn\WebAuthnService;

View::setViewsPath(__DIR__ . '/../resources/views');

// Request-weite Erstauflösung der Oberflächensprache aus der Session (für
// anonyme Seiten wie den Login); für angemeldete Benutzer überschreibt
// AuthGuard::guard() dies anschließend mit der in der Datenbank
// hinterlegten Präferenz, siehe Locale::setFromUser().
Locale::resolveFromSession();

// --- Infrastruktur -----------------------------------------------------
//
// Die DB-Zugangsdaten liegen bewusst NICHT im Projekt-Wurzelverzeichnis
// (also nicht unter dem, was der Webserver als DocumentRoot/Projekt sieht),
// sondern in einer database.yaml außerhalb davon, siehe README.
//
// Reihenfolge der Pfadauflösung:
//   1. Umgebungsvariable DATABASE_CONFIG_PATH (voller, absoluter Pfad), falls gesetzt
//   2. storage/database.yaml (projektrelativ, außerhalb von public/ also nicht
//      vom Webserver ausgeliefert) — hierhin schreibt der Web-Installer
//      (public/install.php) automatisch, siehe dort
//   3. Fester Standardpfad des allerersten Deployments (Altlast, nur noch für
//      Rückwärtskompatibilität mit Installationen von vor Einführung des
//      Installers)
$storageDatabaseConfigPath = __DIR__ . '/../storage/database.yaml';
$legacyDefaultDatabaseConfigPath = '/usr/home/patricya/material_database.yaml';

$databaseConfigPath = getenv('DATABASE_CONFIG_PATH');
if ($databaseConfigPath === false || trim($databaseConfigPath) === '') {
    $databaseConfigPath = is_file($storageDatabaseConfigPath) ? $storageDatabaseConfigPath : $legacyDefaultDatabaseConfigPath;
}

if (!is_file($databaseConfigPath)) {
    throw new \App\Infrastructure\Config\NotInstalledException($databaseConfigPath);
}

$databaseConfig = FlatYamlConfig::load($databaseConfigPath);

$pdo = Connection::fromConfig($databaseConfig);
$fileStorage = new LocalFileStorage(__DIR__ . '/../storage/uploads');

$minuteProgramRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMinuteProgramRepository($pdo);
$minuteProgramItemRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMinuteProgramItemRepository($pdo);
$minuteProgramTemplateRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMinuteProgramTemplateRepository($pdo);
$minuteProgramTemplateItemRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMinuteProgramTemplateItemRepository($pdo);
$minuteProgramShareLinkRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMinuteProgramShareLinkRepository($pdo);
$minuteProgramDetailBuilder = new \App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder($minuteProgramItemRepository);
$noticeDocumentRepository = new \App\Infrastructure\Persistence\Pdo\MariadbNoticeDocumentRepository($pdo);
$noticeDocumentArticleRepository = new \App\Infrastructure\Persistence\Pdo\MariadbNoticeDocumentArticleRepository($pdo);
$categoryRepository = new MariadbCategoryRepository($pdo);
$closingTextTemplateFolderRepository = new \App\Infrastructure\Persistence\Pdo\MariadbClosingTextTemplateFolderRepository($pdo);
$closingTextTemplateRepository = new \App\Infrastructure\Persistence\Pdo\MariadbClosingTextTemplateRepository($pdo);
$manufacturerRepository = new MariadbManufacturerRepository($pdo);
$laborTimeArticleRepository = new MariadbLaborTimeArticleRepository($pdo);
$articleRepository = new MariadbArticleRepository($pdo);
$articleAccessoryRepository = new MariadbArticleAccessoryRepository($pdo);
$articlePreIssueCheckRepository = new \App\Infrastructure\Persistence\Pdo\MariadbArticlePreIssueCheckRepository($pdo);
$articleAdImageRepository = new \App\Infrastructure\Persistence\Pdo\MariadbArticleAdImageRepository($pdo);
$articleChannelListingRepository = new MariadbArticleChannelListingRepository($pdo);
$inventoryNumberRepository = new MariadbInventoryNumberRepository($pdo);
$firmwareVersionRepository = new \App\Infrastructure\Persistence\Pdo\MariadbFirmwareVersionRepository($pdo);
$saleRepository = new \App\Infrastructure\Persistence\Pdo\MariadbSaleRepository($pdo);
$purchaseOrderRepository = new \App\Infrastructure\Persistence\Pdo\MariadbPurchaseOrderRepository($pdo);
$saleStockLotRepository = new \App\Infrastructure\Persistence\Pdo\MariadbSaleStockLotRepository($pdo);
$saleStockConsumptionRepository = new \App\Infrastructure\Persistence\Pdo\MariadbSaleStockConsumptionRepository($pdo);
$rmaRepository = new MariadbRmaRepository($pdo);
$rmaDocumentRepository = new MariadbRmaDocumentRepository($pdo);
$rmaPackagePhotoRepository = new MariadbRmaPackagePhotoRepository($pdo);
$rmaReceiptRepository = new MariadbRmaReceiptRepository($pdo);
$electricalInspectionRepository = new MariadbElectricalInspectionRepository($pdo);
$customerRepository = new MariadbCustomerRepository($pdo);
$caseItemRepository = new MariadbCaseItemRepository($pdo);
$caseInventoryNumberRepository = new MariadbCaseInventoryNumberRepository($pdo);
$caseContentItemRepository = new MariadbCaseContentItemRepository($pdo);
$supplierRepository = new MariadbSupplierRepository($pdo);
$rentalRepository = new MariadbRentalRepository($pdo);
$venueRepository = new MariadbVenueRepository($pdo);
$venuePowerConnectionRepository = new MariadbVenuePowerConnectionRepository($pdo);
$warehouseRepository = new MariadbWarehouseRepository($pdo);
$geocoder = new NominatimGeocoder();
$rentalItemRepository = new MariadbRentalItemRepository($pdo);
$rentalIssuedItemRepository = new MariadbRentalIssuedItemRepository($pdo);
$rentalReminderRepository = new \App\Infrastructure\Persistence\Pdo\MariadbRentalReminderRepository($pdo);
$rentalReminderSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbRentalReminderSettingsRepository($pdo);
$rentalCustomerChecklistTokenRepository = new \App\Infrastructure\Persistence\Pdo\MariadbRentalCustomerChecklistTokenRepository($pdo);
$overageSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbOverageSettingsRepository($pdo);
$vehicleRepository = new \App\Infrastructure\Persistence\Pdo\MariadbVehicleRepository($pdo);
$rentalVehicleLoadRepository = new \App\Infrastructure\Persistence\Pdo\MariadbRentalVehicleLoadRepository($pdo);
$rentalVehicleTowRepository = new \App\Infrastructure\Persistence\Pdo\MariadbRentalVehicleTowRepository($pdo);
$voucherRepository = new \App\Infrastructure\Persistence\Pdo\MariadbVoucherRepository($pdo);
$voucherRedemptionRepository = new \App\Infrastructure\Persistence\Pdo\MariadbVoucherRedemptionRepository($pdo);
$voucherSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbVoucherSettingsRepository($pdo);
$userRepository = new MariadbUserRepository($pdo);
$loginChallengeRepository = new MariadbLoginChallengeRepository($pdo);
$webauthnCredentialRepository = new MariadbWebauthnCredentialRepository($pdo);
$webauthnChallengeRepository = new MariadbWebauthnChallengeRepository($pdo);
$userSessionRepository = new MariadbUserSessionRepository($pdo);
$recordUserSession = new \App\Infrastructure\Http\Auth\RecordUserSession($userSessionRepository);
$impersonationRepository = new \App\Infrastructure\Persistence\Pdo\MariadbImpersonationRepository($pdo);
$webAuthnService = new WebAuthnService();
$smtpSettingsRepository = new MariadbSmtpSettingsRepository($pdo);
$priceTierRepository = new MariadbPriceTierRepository($pdo);
$companySettingsRepository = new MariadbCompanySettingsRepository($pdo);
$googleMerchantSettingsRepository = new MariadbGoogleMerchantSettingsRepository($pdo);
$googleBusinessSettingsRepository = new MariadbGoogleBusinessSettingsRepository($pdo);
$whatsappBusinessSettingsRepository = new MariadbWhatsappBusinessSettingsRepository($pdo);
$integrationBaseSettingsRepository = new MariadbIntegrationBaseSettingsRepository($pdo);
$aiAdImageSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbAiAdImageSettingsRepository($pdo);
$apiSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbApiSettingsRepository($pdo);
$apiTokenRepository = new \App\Infrastructure\Persistence\Pdo\MariadbApiTokenRepository($pdo);
$calendarRepository = new \App\Infrastructure\Persistence\Pdo\MariadbCalendarRepository($pdo);
$calendarAccessRepository = new \App\Infrastructure\Persistence\Pdo\MariadbCalendarAccessRepository($pdo);
$calDavCredentialRepository = new \App\Infrastructure\Persistence\Pdo\MariadbCalDavCredentialRepository($pdo);
$calDavSigningSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbCalDavSigningSettingsRepository($pdo);
$cardDavCredentialRepository = new \App\Infrastructure\Persistence\Pdo\MariadbCardDavCredentialRepository($pdo);
$googleMerchantApiClient = new \App\Infrastructure\Integration\Google\GoogleMerchantApiClient();
$googleBusinessApiClient = new \App\Infrastructure\Integration\Google\GoogleBusinessApiClient();
$whatsappCatalogApiClient = new \App\Infrastructure\Integration\Whatsapp\WhatsappCatalogApiClient();
$articleChannelUrlBuilder = new \App\Application\Article\Channel\ArticleChannelUrlBuilder($integrationBaseSettingsRepository, $googleMerchantSettingsRepository);
$rentalCustomerChecklistTokenIssuer = new \App\Application\Rental\RentalCustomerChecklistTokenIssuer($rentalCustomerChecklistTokenRepository);
$rentalCustomerChecklistUrlBuilder = new \App\Application\Rental\RentalCustomerChecklistUrlBuilder($integrationBaseSettingsRepository);
$articleChannelPublisherRegistry = new \App\Application\Article\Channel\ArticleChannelPublisherRegistry([
    new \App\Application\Article\Channel\GoogleMerchantChannelPublisher($googleMerchantSettingsRepository, $fileStorage, $googleMerchantApiClient),
    new \App\Application\Article\Channel\GoogleBusinessChannelPublisher($googleBusinessSettingsRepository, $googleBusinessApiClient),
    new \App\Application\Article\Channel\WhatsappChannelPublisher($whatsappBusinessSettingsRepository, $whatsappCatalogApiClient),
]);
$legalSettingsRepository = new MariadbLegalSettingsRepository($pdo);
$offerPdfSettingsRepository = new MariadbOfferPdfSettingsRepository($pdo);
$pdfDesignSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbPdfDesignSettingsRepository($pdo);
$pdfLayoutSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbPdfLayoutSettingsRepository($pdo);
$emailTemplateRepository = new MariadbEmailTemplateRepository($pdo);
$emailTemplateRenderer = new EmailTemplateRenderer($emailTemplateRepository, $companySettingsRepository, $fileStorage);
$mailer = new SmtpMailer($smtpSettingsRepository);
$labelPrinterRepository = new MariadbLabelPrinterRepository($pdo);
$labelPrinterDrivers = new LabelPrinterDriverRegistry();
$printerTransport = new TcpPrinterTransport();
$offerRepository = new MariadbOfferRepository($pdo);
$invoiceRepository = new MariadbInvoiceRepository($pdo);
$bankImportStaging = new BankImportStaging($fileStorage);
$invoiceSettingsRepository = new MariadbInvoiceSettingsRepository($pdo);
$pricingSettingsRepository = new MariadbPricingSettingsRepository($pdo);
$invoicePdfSettingsRepository = new MariadbInvoicePdfSettingsRepository($pdo);
$documentNumberingSettingsRepository = new MariadbDocumentNumberingSettingsRepository($pdo);
$documentNumberSequence = new DocumentNumberSequence($pdo, $documentNumberingSettingsRepository);
$dunningLevelRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDunningLevelRepository($pdo);
$dunningNoticeRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDunningNoticeRepository($pdo);
$dunningSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDunningSettingsRepository($pdo);
$dunningPdfGenerator = new \App\Infrastructure\Pdf\DunningPdfGenerator();
$invoiceDueReminderRepository = new \App\Infrastructure\Persistence\Pdo\MariadbInvoiceDueReminderRepository($pdo);
$invoiceDueReminderSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbInvoiceDueReminderSettingsRepository($pdo);
$receiptRepository = new MariadbReceiptRepository($pdo);
$mailboxSettingsRepository = new MariadbMailboxSettingsRepository($pdo);
$receiptInboxAllowedSenderRepository = new MariadbReceiptInboxAllowedSenderRepository($pdo);
$receiptInboxMessageRepository = new MariadbReceiptInboxMessageRepository($pdo);
$receiptInboxAttachmentRepository = new MariadbReceiptInboxAttachmentRepository($pdo);
$pushSettingsRepository = new MariadbPushSettingsRepository($pdo);
$pushSubscriptionRepository = new MariadbPushSubscriptionRepository($pdo);
$messagingSettingsRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMessagingSettingsRepository($pdo);
$messageTemplateRepository = new \App\Infrastructure\Persistence\Pdo\MariadbMessageTemplateRepository($pdo);
$messagingTemplateRenderer = new \App\Application\Messaging\MessagingTemplateRenderer($messageTemplateRepository);
$messageSender = new \App\Infrastructure\Messaging\HttpMessageSender($messagingSettingsRepository);
$documentStorageTargetRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDocumentStorageTargetRepository($pdo);
$documentStorageAssignmentRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDocumentStorageAssignmentRepository($pdo);
$documentStorageUploadLogRepository = new \App\Infrastructure\Persistence\Pdo\MariadbDocumentStorageUploadLogRepository($pdo);
$documentStorageClientFactory = new \App\Infrastructure\DocumentStorage\StorageClientFactory();
$documentStoragePusher = new \App\Infrastructure\DocumentStorage\DocumentStoragePusher(
    $documentStorageAssignmentRepository,
    $documentStorageTargetRepository,
    $documentStorageUploadLogRepository,
    $documentStorageClientFactory,
);
$passwordSetupTokenRepository = new MariadbPasswordSetupTokenRepository($pdo);
$passwordSetupTokenIssuer = new PasswordSetupTokenIssuer($passwordSetupTokenRepository, $mailer, $emailTemplateRenderer);

// --- Backup-Export/-Import (siehe App\Infrastructure\Backup\*) ----------
$backupTempDirectory = __DIR__ . '/../storage/tmp';
$backupDatabaseDumper = new \App\Infrastructure\Persistence\Pdo\MariadbDatabaseDumper($pdo);
$backupDatabaseRestorer = new \App\Infrastructure\Persistence\Pdo\MariadbDatabaseRestorer($pdo);
$backupArchiveBuilder = new \App\Infrastructure\Backup\BackupArchiveBuilder($backupDatabaseDumper, __DIR__ . '/../storage/uploads', $backupTempDirectory);
$backupMigrationRunner = new \App\Infrastructure\Migrations\MigrationRunner($pdo, __DIR__ . '/../database/migrations');
$backupArchiveImporter = new \App\Infrastructure\Backup\BackupArchiveImporter($backupDatabaseRestorer, $backupMigrationRunner, __DIR__ . '/../storage/uploads', $backupTempDirectory);

// --- Command-Bus ---------------------------------------------------------
$commandBus = new CommandBus();

// --- Query-Bus -------------------------------------------------------------
// Bewusst bereits hier (statt erst unten vor den eigentlichen Query-Registrierungen)
// instanziiert: einzelne Query-Registrierungen stehen aus historischen Gründen
// zwischen den Command-Registrierungen weiter oben im Dateiverlauf (z. B.
// Auftragsbestätigungen, Überzugs-/Fälligkeits-Einstellungen) und würden sonst
// "Undefined variable $queryBus" auslösen, da $queryBus zum Zeitpunkt ihrer
// Ausführung noch nicht existierte.
$queryBus = new QueryBus();

$queryBus->register(\App\Application\Settings\Query\GetPdfLayoutSettings::class, new \App\Application\Settings\Query\GetPdfLayoutSettingsHandler($pdfLayoutSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdatePdfLayoutSettings::class, new \App\Application\Settings\Command\UpdatePdfLayoutSettingsHandler($pdfLayoutSettingsRepository));

$commandBus->register(\App\Application\Category\Command\CreateCategory::class, new CreateCategoryHandler($categoryRepository));
$commandBus->register(\App\Application\Category\Command\UpdateCategory::class, new UpdateCategoryHandler($categoryRepository));
$commandBus->register(\App\Application\Category\Command\DeleteCategory::class, new DeleteCategoryHandler($categoryRepository, $articleRepository));

$commandBus->register(\App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplateFolder::class, new \App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplateFolderHandler($closingTextTemplateFolderRepository));
$commandBus->register(\App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplateFolder::class, new \App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplateFolderHandler($closingTextTemplateFolderRepository));
$commandBus->register(\App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplateFolder::class, new \App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplateFolderHandler($closingTextTemplateFolderRepository, $closingTextTemplateRepository));
$commandBus->register(\App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplate::class, new \App\Application\ClosingTextTemplate\Command\DeleteClosingTextTemplateHandler($closingTextTemplateRepository));
// CreateClosingTextTemplate/UpdateClosingTextTemplate werden weiter unten
// registriert (siehe dort), da sie $htmlSanitizer benötigen, das erst
// später instanziiert wird.

$commandBus->register(\App\Application\Manufacturer\Command\CreateManufacturer::class, new CreateManufacturerHandler($manufacturerRepository));
$commandBus->register(\App\Application\Manufacturer\Command\UpdateManufacturer::class, new UpdateManufacturerHandler($manufacturerRepository));
$commandBus->register(\App\Application\Manufacturer\Command\DeleteManufacturer::class, new DeleteManufacturerHandler($manufacturerRepository));
$commandBus->register(\App\Application\LaborTime\Command\CreateLaborTimeArticle::class, new CreateLaborTimeArticleHandler($laborTimeArticleRepository));
$commandBus->register(\App\Application\LaborTime\Command\UpdateLaborTimeArticle::class, new UpdateLaborTimeArticleHandler($laborTimeArticleRepository));
$commandBus->register(\App\Application\LaborTime\Command\DeleteLaborTimeArticle::class, new DeleteLaborTimeArticleHandler($laborTimeArticleRepository));

$commandBus->register(\App\Application\Article\Command\CreateArticle::class, new CreateArticleHandler($articleRepository, $manufacturerRepository, $categoryRepository));
$commandBus->register(\App\Application\Article\Command\UpdateArticle::class, new UpdateArticleHandler($articleRepository, $manufacturerRepository, $categoryRepository));
$commandBus->register(\App\Application\Article\Command\DeleteArticle::class, new DeleteArticleHandler($articleRepository, $fileStorage, $caseContentItemRepository, $rentalItemRepository, $articleChannelListingRepository, $articleChannelPublisherRegistry));
$commandBus->register(\App\Application\Article\Command\SetArticleActive::class, new \App\Application\Article\Command\SetArticleActiveHandler($articleRepository));
$commandBus->register(\App\Application\Article\Command\AddArticleImage::class, new AddArticleImageHandler($articleRepository, $fileStorage));
$commandBus->register(\App\Application\Article\Command\DeleteArticleImage::class, new DeleteArticleImageHandler($articleRepository, $fileStorage));
$commandBus->register(\App\Application\Article\Command\AddInventoryNumber::class, new AddInventoryNumberHandler($inventoryNumberRepository, $articleRepository, $manufacturerRepository, $supplierRepository, $receiptRepository, $firmwareVersionRepository));
$commandBus->register(\App\Application\Article\Command\UpdateInventoryNumber::class, new UpdateInventoryNumberHandler($inventoryNumberRepository, $supplierRepository, $receiptRepository, $articleRepository, $firmwareVersionRepository));
$commandBus->register(\App\Application\Article\Command\AddFirmwareVersion::class, new \App\Application\Article\Command\AddFirmwareVersionHandler($firmwareVersionRepository, $articleRepository));
$commandBus->register(\App\Application\Article\Command\DeleteFirmwareVersion::class, new \App\Application\Article\Command\DeleteFirmwareVersionHandler($firmwareVersionRepository, $inventoryNumberRepository));
$commandBus->register(\App\Application\Article\Command\DeleteInventoryNumber::class, new DeleteInventoryNumberHandler($inventoryNumberRepository, $rmaRepository, $saleRepository));
$commandBus->register(\App\Application\Article\Command\AddArticleAccessory::class, new AddArticleAccessoryHandler($articleAccessoryRepository, $articleRepository));
$commandBus->register(\App\Application\Article\Command\UpdateArticleAccessory::class, new UpdateArticleAccessoryHandler($articleAccessoryRepository));
$commandBus->register(\App\Application\Article\Command\DeleteArticleAccessory::class, new DeleteArticleAccessoryHandler($articleAccessoryRepository));
$commandBus->register(\App\Application\Article\Command\AddArticlePreIssueCheck::class, new \App\Application\Article\Command\AddArticlePreIssueCheckHandler($articlePreIssueCheckRepository, $articleRepository));
$commandBus->register(\App\Application\Article\Command\UpdateArticlePreIssueCheck::class, new \App\Application\Article\Command\UpdateArticlePreIssueCheckHandler($articlePreIssueCheckRepository));
$commandBus->register(\App\Application\Article\Command\DeleteArticlePreIssueCheck::class, new \App\Application\Article\Command\DeleteArticlePreIssueCheckHandler($articlePreIssueCheckRepository));
$openAiImageClient = new \App\Infrastructure\Integration\OpenAi\OpenAiImageClient();
$commandBus->register(\App\Application\Article\Command\GenerateArticleAdImage::class, new \App\Application\Article\Command\GenerateArticleAdImageHandler(
    $articleRepository,
    $manufacturerRepository,
    $categoryRepository,
    $articleAdImageRepository,
    $fileStorage,
    $aiAdImageSettingsRepository,
    $openAiImageClient,
));
$commandBus->register(\App\Application\Article\Command\DeleteArticleAdImage::class, new \App\Application\Article\Command\DeleteArticleAdImageHandler($articleAdImageRepository, $fileStorage));
$commandBus->register(\App\Application\Article\Command\SetArticleChannelEnabled::class, new \App\Application\Article\Command\SetArticleChannelEnabledHandler($articleRepository, $articleChannelListingRepository, $articleChannelPublisherRegistry, $articleChannelUrlBuilder));
$commandBus->register(\App\Application\Article\Command\SyncArticleChannel::class, new \App\Application\Article\Command\SyncArticleChannelHandler($articleRepository, $articleChannelListingRepository, $articleChannelPublisherRegistry, $articleChannelUrlBuilder));

$commandBus->register(\App\Application\Rma\Command\CreateRma::class, new CreateRmaHandler($rmaRepository, $inventoryNumberRepository, $rmaReceiptRepository));
$commandBus->register(\App\Application\Rma\Command\UpdateRma::class, new UpdateRmaHandler($rmaRepository, $rmaReceiptRepository));
$commandBus->register(\App\Application\Rma\Command\DeleteRma::class, new DeleteRmaHandler($rmaRepository, $rmaDocumentRepository, $rmaPackagePhotoRepository, $fileStorage));
$commandBus->register(\App\Application\Rma\Command\AttachRmaDocument::class, new AttachRmaDocumentHandler($rmaRepository, $rmaDocumentRepository, $fileStorage));
$commandBus->register(\App\Application\Rma\Command\DeleteRmaDocument::class, new DeleteRmaDocumentHandler($rmaDocumentRepository, $fileStorage));
$commandBus->register(\App\Application\Rma\Command\AttachRmaPackagePhoto::class, new AttachRmaPackagePhotoHandler($rmaRepository, $rmaPackagePhotoRepository, $fileStorage));
$commandBus->register(\App\Application\Rma\Command\DeleteRmaPackagePhoto::class, new DeleteRmaPackagePhotoHandler($rmaPackagePhotoRepository, $fileStorage));

$commandBus->register(\App\Application\ElectricalInspection\Command\CreateElectricalInspection::class, new CreateElectricalInspectionHandler($electricalInspectionRepository, $inventoryNumberRepository));
$commandBus->register(\App\Application\ElectricalInspection\Command\UpdateElectricalInspection::class, new UpdateElectricalInspectionHandler($electricalInspectionRepository));
$commandBus->register(\App\Application\ElectricalInspection\Command\DeleteElectricalInspection::class, new DeleteElectricalInspectionHandler($electricalInspectionRepository));

$commandBus->register(\App\Application\Customer\Command\CreateCustomer::class, new CreateCustomerHandler($customerRepository, $documentNumberSequence, $pdo));
$commandBus->register(\App\Application\Customer\Command\ImportCustomersFromCsv::class, new \App\Application\Customer\Command\ImportCustomersFromCsvHandler($customerRepository, $documentNumberSequence, $pdo));
$commandBus->register(\App\Application\Customer\Command\UpdateCustomer::class, new UpdateCustomerHandler($customerRepository));
$commandBus->register(\App\Application\Customer\Command\DeleteCustomer::class, new DeleteCustomerHandler($customerRepository));

$commandBus->register(\App\Application\CaseItem\Command\CreateCaseItem::class, new CreateCaseItemHandler($caseItemRepository, $manufacturerRepository));
$commandBus->register(\App\Application\CaseItem\Command\UpdateCaseItem::class, new UpdateCaseItemHandler($caseItemRepository, $manufacturerRepository));
$commandBus->register(\App\Application\CaseItem\Command\DeleteCaseItem::class, new DeleteCaseItemHandler($caseItemRepository));
$commandBus->register(\App\Application\CaseItem\Command\AddCaseInventoryNumber::class, new AddCaseInventoryNumberHandler($caseInventoryNumberRepository, $caseItemRepository, $manufacturerRepository));
$commandBus->register(\App\Application\CaseItem\Command\UpdateCaseInventoryNumber::class, new UpdateCaseInventoryNumberHandler($caseInventoryNumberRepository));
$commandBus->register(\App\Application\CaseItem\Command\DeleteCaseInventoryNumber::class, new DeleteCaseInventoryNumberHandler($caseInventoryNumberRepository));
$commandBus->register(\App\Application\CaseItem\Command\AddCaseContentItem::class, new AddCaseContentItemHandler($caseContentItemRepository, $caseInventoryNumberRepository, $articleRepository));
$commandBus->register(\App\Application\CaseItem\Command\UpdateCaseContentItem::class, new UpdateCaseContentItemHandler($caseContentItemRepository));
$commandBus->register(\App\Application\CaseItem\Command\DeleteCaseContentItem::class, new DeleteCaseContentItemHandler($caseContentItemRepository));

$commandBus->register(\App\Application\Supplier\Command\CreateSupplier::class, new CreateSupplierHandler($supplierRepository));
$commandBus->register(\App\Application\Supplier\Command\UpdateSupplier::class, new UpdateSupplierHandler($supplierRepository));
$commandBus->register(\App\Application\Supplier\Command\DeleteSupplier::class, new DeleteSupplierHandler($supplierRepository));

$commandBus->register(\App\Application\Vehicle\Command\CreateVehicle::class, new \App\Application\Vehicle\Command\CreateVehicleHandler($vehicleRepository));
$commandBus->register(\App\Application\Vehicle\Command\UpdateVehicle::class, new \App\Application\Vehicle\Command\UpdateVehicleHandler($vehicleRepository));
$commandBus->register(\App\Application\Vehicle\Command\DeleteVehicle::class, new \App\Application\Vehicle\Command\DeleteVehicleHandler($vehicleRepository));
$commandBus->register(\App\Application\Vehicle\Command\AssignRentalVehicleLoad::class, new \App\Application\Vehicle\Command\AssignRentalVehicleLoadHandler($rentalIssuedItemRepository, $vehicleRepository, $rentalVehicleLoadRepository));
$commandBus->register(\App\Application\Vehicle\Command\RemoveRentalVehicleLoad::class, new \App\Application\Vehicle\Command\RemoveRentalVehicleLoadHandler($rentalIssuedItemRepository, $rentalVehicleLoadRepository));
$commandBus->register(\App\Application\Vehicle\Command\SetRentalVehicleTow::class, new \App\Application\Vehicle\Command\SetRentalVehicleTowHandler($rentalRepository, $vehicleRepository, $rentalVehicleTowRepository));
$commandBus->register(\App\Application\Vehicle\Command\RemoveRentalVehicleTow::class, new \App\Application\Vehicle\Command\RemoveRentalVehicleTowHandler($rentalVehicleTowRepository));

$commandBus->register(\App\Application\Voucher\Command\CreateVoucher::class, new \App\Application\Voucher\Command\CreateVoucherHandler($voucherRepository, $voucherSettingsRepository, $customerRepository));
$commandBus->register(\App\Application\Voucher\Command\UpdateVoucher::class, new \App\Application\Voucher\Command\UpdateVoucherHandler($voucherRepository, $customerRepository));
$commandBus->register(\App\Application\Voucher\Command\DeleteVoucher::class, new \App\Application\Voucher\Command\DeleteVoucherHandler($voucherRepository, $voucherRedemptionRepository));
$commandBus->register(\App\Application\Voucher\Command\RedeemVoucherForRental::class, new \App\Application\Voucher\Command\RedeemVoucherForRentalHandler($voucherRepository, $voucherRedemptionRepository, $rentalRepository));
$commandBus->register(\App\Application\Voucher\Command\RemoveVoucherRedemption::class, new \App\Application\Voucher\Command\RemoveVoucherRedemptionHandler($voucherRedemptionRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateVoucherSettings::class, new \App\Application\Settings\Command\UpdateVoucherSettingsHandler($voucherSettingsRepository));

$commandBus->register(\App\Application\Rental\Command\CreateRental::class, new CreateRentalHandler($rentalRepository, $customerRepository, $venueRepository));
$commandBus->register(\App\Application\Rental\Command\UpdateRental::class, new UpdateRentalHandler($rentalRepository, $customerRepository, $venueRepository));

$commandBus->register(\App\Application\Venue\Command\CreateVenue::class, new CreateVenueHandler($venueRepository, $geocoder));
$commandBus->register(\App\Application\Venue\Command\UpdateVenue::class, new UpdateVenueHandler($venueRepository, $geocoder));
$commandBus->register(\App\Application\Venue\Command\DeleteVenue::class, new DeleteVenueHandler($venueRepository));
$commandBus->register(\App\Application\Venue\Command\AddVenuePowerConnection::class, new AddVenuePowerConnectionHandler($venuePowerConnectionRepository, $venueRepository));
$commandBus->register(\App\Application\Venue\Command\UpdateVenuePowerConnection::class, new UpdateVenuePowerConnectionHandler($venuePowerConnectionRepository));
$commandBus->register(\App\Application\Venue\Command\DeleteVenuePowerConnection::class, new DeleteVenuePowerConnectionHandler($venuePowerConnectionRepository));
$commandBus->register(\App\Application\Warehouse\Command\CreateWarehouse::class, new CreateWarehouseHandler($warehouseRepository, $geocoder));
$commandBus->register(\App\Application\Warehouse\Command\UpdateWarehouse::class, new UpdateWarehouseHandler($warehouseRepository, $geocoder));
$commandBus->register(\App\Application\Warehouse\Command\DeleteWarehouse::class, new DeleteWarehouseHandler($warehouseRepository));
$commandBus->register(\App\Application\Rental\Command\UpdateRentalLogistics::class, new UpdateRentalLogisticsHandler($rentalRepository, $warehouseRepository));
$commandBus->register(\App\Application\Rental\Command\DeleteRental::class, new DeleteRentalHandler($rentalRepository));
$commandBus->register(\App\Application\Rental\Command\AddRentalItem::class, new AddRentalItemHandler($rentalItemRepository, $rentalRepository, $articleRepository, $inventoryNumberRepository));
$commandBus->register(\App\Application\Rental\Command\UpdateRentalItem::class, new UpdateRentalItemHandler($rentalItemRepository, $rentalIssuedItemRepository, $inventoryNumberRepository));
$commandBus->register(\App\Application\Rental\Command\DeleteRentalItem::class, new DeleteRentalItemHandler($rentalItemRepository, $rentalIssuedItemRepository));
$commandBus->register(\App\Application\Rental\Command\IssueRentalItem::class, new IssueRentalItemHandler($rentalIssuedItemRepository, $rentalItemRepository, $rentalRepository, $inventoryNumberRepository, $articleAccessoryRepository, $articlePreIssueCheckRepository, $saleRepository, $customerRepository));
$commandBus->register(\App\Application\Rental\Command\SignRentalTerms::class, new \App\Application\Rental\Command\SignRentalTermsHandler($rentalRepository, $fileStorage));
$commandBus->register(\App\Application\Rental\Command\ConfirmCustomerIdChecked::class, new \App\Application\Rental\Command\ConfirmCustomerIdCheckedHandler($customerRepository));
$commandBus->register(\App\Application\Sale\Command\SellInventoryNumber::class, new \App\Application\Sale\Command\SellInventoryNumberHandler($inventoryNumberRepository, $articleRepository, $customerRepository, $invoiceRepository, $saleRepository));
$commandBus->register(\App\Application\Sale\Command\SellArticleFromStock::class, new \App\Application\Sale\Command\SellArticleFromStockHandler($articleRepository, $customerRepository, $invoiceRepository, $saleRepository, $saleStockLotRepository, $saleStockConsumptionRepository));
$commandBus->register(\App\Application\Purchase\Command\CreatePurchaseOrder::class, new \App\Application\Purchase\Command\CreatePurchaseOrderHandler($purchaseOrderRepository, $supplierRepository, $articleRepository));
$commandBus->register(\App\Application\Purchase\Command\UpdatePurchaseOrder::class, new \App\Application\Purchase\Command\UpdatePurchaseOrderHandler($purchaseOrderRepository, $supplierRepository, $articleRepository));
$commandBus->register(\App\Application\Purchase\Command\DeletePurchaseOrder::class, new \App\Application\Purchase\Command\DeletePurchaseOrderHandler($purchaseOrderRepository));
$commandBus->register(\App\Application\Purchase\Command\MarkPurchaseOrderOrdered::class, new \App\Application\Purchase\Command\MarkPurchaseOrderOrderedHandler($purchaseOrderRepository));
$commandBus->register(\App\Application\Purchase\Command\MarkPurchaseOrderCancelled::class, new \App\Application\Purchase\Command\MarkPurchaseOrderCancelledHandler($purchaseOrderRepository));
$commandBus->register(\App\Application\Purchase\Command\ReceivePurchaseOrderItem::class, new \App\Application\Purchase\Command\ReceivePurchaseOrderItemHandler($purchaseOrderRepository, $inventoryNumberRepository, $articleRepository, $manufacturerRepository, $saleStockLotRepository));
$commandBus->register(\App\Application\Rental\Command\UnissueRentalItem::class, new UnissueRentalItemHandler($rentalIssuedItemRepository));
$commandBus->register(\App\Application\Rental\Command\ConfirmRentalItemAccessories::class, new ConfirmRentalItemAccessoriesHandler($rentalItemRepository));
$commandBus->register(\App\Application\Rental\Command\ConfirmRentalItemPreIssueChecks::class, new \App\Application\Rental\Command\ConfirmRentalItemPreIssueChecksHandler($rentalItemRepository));
$commandBus->register(\App\Application\Rental\Command\ReturnRentalItem::class, new ReturnRentalItemHandler($rentalIssuedItemRepository, $rentalRepository, $rmaRepository));
$commandBus->register(\App\Application\Rental\Command\UndoReturnRentalItem::class, new UndoReturnRentalItemHandler($rentalIssuedItemRepository));

// Minutenprogramme (Ablaufpläne), siehe App\Domain\MinuteProgram\MinuteProgram.
$commandBus->register(\App\Application\MinuteProgram\Command\CreateMinuteProgram::class, new \App\Application\MinuteProgram\Command\CreateMinuteProgramHandler($minuteProgramRepository, $rentalRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\UpdateMinuteProgram::class, new \App\Application\MinuteProgram\Command\UpdateMinuteProgramHandler($minuteProgramRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\DeleteMinuteProgram::class, new \App\Application\MinuteProgram\Command\DeleteMinuteProgramHandler($minuteProgramRepository, $minuteProgramItemRepository, $minuteProgramShareLinkRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\SetMinuteProgramDelay::class, new \App\Application\MinuteProgram\Command\SetMinuteProgramDelayHandler($minuteProgramRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\AddMinuteProgramItem::class, new \App\Application\MinuteProgram\Command\AddMinuteProgramItemHandler($minuteProgramItemRepository, $minuteProgramRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\UpdateMinuteProgramItem::class, new \App\Application\MinuteProgram\Command\UpdateMinuteProgramItemHandler($minuteProgramItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\DeleteMinuteProgramItem::class, new \App\Application\MinuteProgram\Command\DeleteMinuteProgramItemHandler($minuteProgramItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\MoveMinuteProgramItem::class, new \App\Application\MinuteProgram\Command\MoveMinuteProgramItemHandler($minuteProgramItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\CreateMinuteProgramFromTemplate::class, new \App\Application\MinuteProgram\Command\CreateMinuteProgramFromTemplateHandler($minuteProgramTemplateRepository, $minuteProgramTemplateItemRepository, $minuteProgramRepository, $minuteProgramItemRepository, $rentalRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\CreateMinuteProgramTemplate::class, new \App\Application\MinuteProgram\Command\CreateMinuteProgramTemplateHandler($minuteProgramTemplateRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\CreateMinuteProgramTemplateFromProgram::class, new \App\Application\MinuteProgram\Command\CreateMinuteProgramTemplateFromProgramHandler($minuteProgramRepository, $minuteProgramItemRepository, $minuteProgramTemplateRepository, $minuteProgramTemplateItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplate::class, new \App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplateHandler($minuteProgramTemplateRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplate::class, new \App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplateHandler($minuteProgramTemplateRepository, $minuteProgramTemplateItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\AddMinuteProgramTemplateItem::class, new \App\Application\MinuteProgram\Command\AddMinuteProgramTemplateItemHandler($minuteProgramTemplateItemRepository, $minuteProgramTemplateRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplateItem::class, new \App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplateItemHandler($minuteProgramTemplateItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplateItem::class, new \App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplateItemHandler($minuteProgramTemplateItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\MoveMinuteProgramTemplateItem::class, new \App\Application\MinuteProgram\Command\MoveMinuteProgramTemplateItemHandler($minuteProgramTemplateItemRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\CreateMinuteProgramShareLink::class, new \App\Application\MinuteProgram\Command\CreateMinuteProgramShareLinkHandler($minuteProgramShareLinkRepository, $minuteProgramRepository));
$commandBus->register(\App\Application\MinuteProgram\Command\RevokeMinuteProgramShareLink::class, new \App\Application\MinuteProgram\Command\RevokeMinuteProgramShareLinkHandler($minuteProgramShareLinkRepository));

// Hinweisdokumente, siehe App\Domain\NoticeDocument\NoticeDocument.
$commandBus->register(\App\Application\NoticeDocument\Command\UploadNoticeDocument::class, new \App\Application\NoticeDocument\Command\UploadNoticeDocumentHandler($noticeDocumentRepository, $noticeDocumentArticleRepository, $fileStorage));
$commandBus->register(\App\Application\NoticeDocument\Command\RemoveNoticeDocumentFromArticle::class, new \App\Application\NoticeDocument\Command\RemoveNoticeDocumentFromArticleHandler($noticeDocumentRepository, $noticeDocumentArticleRepository));
$commandBus->register(\App\Application\NoticeDocument\Command\UpdateNoticeDocumentAssignment::class, new \App\Application\NoticeDocument\Command\UpdateNoticeDocumentAssignmentHandler($noticeDocumentRepository, $noticeDocumentArticleRepository));
$commandBus->register(\App\Application\NoticeDocument\Command\DeleteNoticeDocument::class, new \App\Application\NoticeDocument\Command\DeleteNoticeDocumentHandler($noticeDocumentRepository, $noticeDocumentArticleRepository, $fileStorage));

$commandBus->register(\App\Application\User\Command\CreateUser::class, new CreateUserHandler($userRepository, $passwordSetupTokenIssuer));
$commandBus->register(\App\Application\User\Command\UpdateUser::class, new UpdateUserHandler($userRepository));
$commandBus->register(\App\Application\User\Command\DeleteUser::class, new DeleteUserHandler($userRepository));
$commandBus->register(\App\Application\User\Command\ChangePassword::class, new ChangePasswordHandler($userRepository));
$commandBus->register(\App\Application\User\Command\ChangeOwnPassword::class, new ChangeOwnPasswordHandler($userRepository));
$commandBus->register(\App\Application\User\Command\EnableTotp::class, new EnableTotpHandler($userRepository));
$commandBus->register(\App\Application\User\Command\EnableEmailOtp::class, new EnableEmailOtpHandler($userRepository));
$commandBus->register(\App\Application\User\Command\DisableTwoFactor::class, new DisableTwoFactorHandler($userRepository));
$commandBus->register(\App\Application\User\Command\UpdateDefaultPageSize::class, new UpdateDefaultPageSizeHandler($userRepository));
$commandBus->register(\App\Application\User\Command\UpdateDashboardWidgets::class, new \App\Application\User\Command\UpdateDashboardWidgetsHandler($userRepository));
$commandBus->register(\App\Application\User\Command\UpdateLocale::class, new UpdateLocaleHandler($userRepository));

$commandBus->register(\App\Application\Auth\Command\AuthenticatePassword::class, new AuthenticatePasswordHandler($userRepository));
$commandBus->register(\App\Application\Auth\Command\RequestEmailOtp::class, new RequestEmailOtpHandler($userRepository, $loginChallengeRepository, $mailer, $emailTemplateRenderer));
$commandBus->register(\App\Application\Auth\Command\VerifyTwoFactorCode::class, new VerifyTwoFactorCodeHandler($userRepository, $loginChallengeRepository));

$commandBus->register(\App\Application\Auth\Command\BeginPasskeyRegistration::class, new BeginPasskeyRegistrationHandler($userRepository, $webauthnCredentialRepository, $webauthnChallengeRepository, $webAuthnService));
$commandBus->register(\App\Application\Auth\Command\CompletePasskeyRegistration::class, new CompletePasskeyRegistrationHandler($webauthnCredentialRepository, $webauthnChallengeRepository, $webAuthnService));
$commandBus->register(\App\Application\Auth\Command\BeginPasskeyLogin::class, new BeginPasskeyLoginHandler($webauthnChallengeRepository, $webAuthnService));
$commandBus->register(\App\Application\Auth\Command\CompletePasskeyLogin::class, new CompletePasskeyLoginHandler($userRepository, $webauthnCredentialRepository, $webauthnChallengeRepository, $webAuthnService));
$commandBus->register(\App\Application\Auth\Command\DeletePasskey::class, new DeletePasskeyHandler($webauthnCredentialRepository));

$commandBus->register(\App\Application\Auth\Command\RequestPasswordSetup::class, new RequestPasswordSetupHandler($userRepository, $passwordSetupTokenIssuer));
$commandBus->register(\App\Application\Auth\Command\CompletePasswordSetup::class, new CompletePasswordSetupHandler($passwordSetupTokenRepository, $userRepository));

$commandBus->register(\App\Application\Auth\Command\RevokeSession::class, new \App\Application\Auth\Command\RevokeSessionHandler($userSessionRepository));
$commandBus->register(\App\Application\Auth\Command\RevokeOtherSessions::class, new \App\Application\Auth\Command\RevokeOtherSessionsHandler($userSessionRepository));
$commandBus->register(\App\Application\Auth\Command\AdminRevokeSession::class, new \App\Application\Auth\Command\AdminRevokeSessionHandler($userSessionRepository));
$commandBus->register(\App\Application\Auth\Command\AdminRevokeAllSessions::class, new \App\Application\Auth\Command\AdminRevokeAllSessionsHandler($userSessionRepository));
$commandBus->register(\App\Application\Auth\Command\StartImpersonation::class, new \App\Application\Auth\Command\StartImpersonationHandler($userRepository, $impersonationRepository));
$commandBus->register(\App\Application\Auth\Command\StopImpersonation::class, new \App\Application\Auth\Command\StopImpersonationHandler($impersonationRepository));

$commandBus->register(\App\Application\Settings\Command\UpdateSmtpSettings::class, new UpdateSmtpSettingsHandler($smtpSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\SendTestMail::class, new SendTestMailHandler($mailer));
$commandBus->register(\App\Application\Settings\Command\UpdatePriceTiers::class, new UpdatePriceTiersHandler($priceTierRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateCompanySettings::class, new UpdateCompanySettingsHandler($companySettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateLegalSettings::class, new UpdateLegalSettingsHandler($legalSettingsRepository));
$htmlSanitizer = new HtmlSanitizer();
$commandBus->register(\App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplate::class, new \App\Application\ClosingTextTemplate\Command\CreateClosingTextTemplateHandler($closingTextTemplateRepository, $closingTextTemplateFolderRepository, $htmlSanitizer));
$commandBus->register(\App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplate::class, new \App\Application\ClosingTextTemplate\Command\UpdateClosingTextTemplateHandler($closingTextTemplateRepository, $closingTextTemplateFolderRepository, $htmlSanitizer));
$richTextPdfRenderer = new RichTextPdfRenderer(new HtmlToRichTextParser());
$commandBus->register(\App\Application\Settings\Command\UpdateOfferPdfSettings::class, new UpdateOfferPdfSettingsHandler($offerPdfSettingsRepository, $htmlSanitizer));
$commandBus->register(\App\Application\Mail\Command\SaveEmailTemplate::class, new \App\Application\Mail\Command\SaveEmailTemplateHandler($emailTemplateRepository));
$commandBus->register(\App\Application\Mail\Command\ResetEmailTemplate::class, new \App\Application\Mail\Command\ResetEmailTemplateHandler($emailTemplateRepository));
$commandBus->register(\App\Application\Settings\Command\UploadCompanyLogo::class, new UploadCompanyLogoHandler($companySettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\DeleteCompanyLogo::class, new DeleteCompanyLogoHandler($companySettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\UploadCompanyDarkLogo::class, new UploadCompanyDarkLogoHandler($companySettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\DeleteCompanyDarkLogo::class, new DeleteCompanyDarkLogoHandler($companySettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\UploadCompanyBackgroundImage::class, new UploadCompanyBackgroundImageHandler($companySettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\DeleteCompanyBackgroundImage::class, new DeleteCompanyBackgroundImageHandler($companySettingsRepository, $fileStorage));

$commandBus->register(\App\Application\Printing\Command\CreateLabelPrinter::class, new CreateLabelPrinterHandler($labelPrinterRepository));
$commandBus->register(\App\Application\Printing\Command\UpdateLabelPrinter::class, new UpdateLabelPrinterHandler($labelPrinterRepository));
$commandBus->register(\App\Application\Printing\Command\DeleteLabelPrinter::class, new DeleteLabelPrinterHandler($labelPrinterRepository));
$commandBus->register(\App\Application\Printing\Command\SendTestPrint::class, new SendTestPrintHandler($labelPrinterRepository, $labelPrinterDrivers));
$commandBus->register(\App\Application\Printing\Command\PrintInventoryNumberLabel::class, new PrintInventoryNumberLabelHandler($inventoryNumberRepository, $labelPrinterRepository, $labelPrinterDrivers, $printerTransport));

$commandBus->register(\App\Application\Offer\Command\CreateOffer::class, new CreateOfferHandler($offerRepository, $customerRepository, $venueRepository, $articleRepository, $pricingSettingsRepository, $laborTimeArticleRepository, $inventoryNumberRepository, $saleRepository, $userRepository, $documentNumberSequence, $pdo, $htmlSanitizer));
$commandBus->register(\App\Application\Offer\Command\UpdateOffer::class, new UpdateOfferHandler($offerRepository, $customerRepository, $venueRepository, $articleRepository, $pricingSettingsRepository, $laborTimeArticleRepository, $inventoryNumberRepository, $saleRepository, $userRepository, $htmlSanitizer));
$commandBus->register(\App\Application\Offer\Command\MarkOfferSent::class, new MarkOfferSentHandler($offerRepository));
$commandBus->register(\App\Application\Offer\Command\MarkOfferAccepted::class, new MarkOfferAcceptedHandler($offerRepository));
$commandBus->register(\App\Application\Offer\Command\MarkOfferDeclined::class, new MarkOfferDeclinedHandler($offerRepository));
$commandBus->register(\App\Application\Offer\Command\DeleteOffer::class, new DeleteOfferHandler($offerRepository));
$commandBus->register(\App\Application\Offer\Command\ConvertOfferToRental::class, new ConvertOfferToRentalHandler($offerRepository, $rentalRepository, $rentalItemRepository, $inventoryNumberRepository));
$offerPdfGenerator = new OfferPdfGenerator($fileStorage, $richTextPdfRenderer);
$orderConfirmationRepository = new \App\Infrastructure\Persistence\Pdo\MariadbOrderConfirmationRepository($pdo);
$orderConfirmationPdfGenerator = new OrderConfirmationPdfGenerator($fileStorage, $richTextPdfRenderer);
$getOfferHandler = new GetOfferHandler($offerRepository, $customerRepository, $articleRepository, $laborTimeArticleRepository, $inventoryNumberRepository, $manufacturerRepository, $userRepository);
$commandBus->register(\App\Application\Offer\Command\EmailOfferPdf::class, new EmailOfferPdfHandler(
    $getOfferHandler,
    $companySettingsRepository,
    $legalSettingsRepository,
    $offerPdfSettingsRepository,
    $pdfDesignSettingsRepository,
    $pdfLayoutSettingsRepository,
    $offerPdfGenerator,
    $mailer,
    $emailTemplateRenderer,
    $documentStoragePusher,
));

// --- Auftragsbestätigungen (Order Confirmations) -----------------------
$commandBus->register(\App\Application\OrderConfirmation\Command\CreateOrderConfirmation::class, new \App\Application\OrderConfirmation\Command\CreateOrderConfirmationHandler(
    $orderConfirmationRepository,
    $customerRepository,
    $venueRepository,
    $articleRepository,
    $pricingSettingsRepository,
    $documentNumberSequence,
    $pdo,
    $htmlSanitizer,
));
$commandBus->register(\App\Application\OrderConfirmation\Command\CreateOrderConfirmationFromOffer::class, new \App\Application\OrderConfirmation\Command\CreateOrderConfirmationFromOfferHandler(
    $offerRepository,
    $orderConfirmationRepository,
    $documentNumberSequence,
    $pdo,
));
$commandBus->register(\App\Application\OrderConfirmation\Command\UpdateOrderConfirmation::class, new \App\Application\OrderConfirmation\Command\UpdateOrderConfirmationHandler(
    $orderConfirmationRepository,
    $customerRepository,
    $venueRepository,
    $articleRepository,
    $pricingSettingsRepository,
    $htmlSanitizer,
));
$commandBus->register(\App\Application\OrderConfirmation\Command\DeleteOrderConfirmation::class, new \App\Application\OrderConfirmation\Command\DeleteOrderConfirmationHandler($orderConfirmationRepository));
$commandBus->register(\App\Application\OrderConfirmation\Command\MarkOrderConfirmationSent::class, new \App\Application\OrderConfirmation\Command\MarkOrderConfirmationSentHandler($orderConfirmationRepository));
$commandBus->register(\App\Application\OrderConfirmation\Command\MarkOrderConfirmationConfirmed::class, new \App\Application\OrderConfirmation\Command\MarkOrderConfirmationConfirmedHandler($orderConfirmationRepository));
$commandBus->register(\App\Application\OrderConfirmation\Command\MarkOrderConfirmationCancelled::class, new \App\Application\OrderConfirmation\Command\MarkOrderConfirmationCancelledHandler($orderConfirmationRepository));
$queryBus->register(\App\Application\OrderConfirmation\Query\GetOrderConfirmation::class, new \App\Application\OrderConfirmation\Query\GetOrderConfirmationHandler($orderConfirmationRepository, $customerRepository, $articleRepository, $manufacturerRepository));
$queryBus->register(\App\Application\OrderConfirmation\Query\ListOrderConfirmations::class, new \App\Application\OrderConfirmation\Query\ListOrderConfirmationsHandler($orderConfirmationRepository, $customerRepository));

// --- Rechnungen (Invoices) --------------------------------------------
$commandBus->register(\App\Application\Invoice\Command\CreateInvoice::class, new CreateInvoiceHandler($invoiceRepository, $customerRepository, $venueRepository, $articleRepository, $pricingSettingsRepository, $laborTimeArticleRepository, $userRepository, $htmlSanitizer, $rentalRepository));
$commandBus->register(\App\Application\Invoice\Command\UpdateInvoice::class, new UpdateInvoiceHandler($invoiceRepository, $customerRepository, $venueRepository, $articleRepository, $pricingSettingsRepository, $laborTimeArticleRepository, $userRepository, $htmlSanitizer, $rentalRepository));
$commandBus->register(\App\Application\Invoice\Command\DeleteInvoice::class, new DeleteInvoiceHandler($invoiceRepository));
$commandBus->register(\App\Application\Invoice\Command\MarkInvoiceSent::class, new MarkInvoiceSentHandler($invoiceRepository, $invoiceSettingsRepository, $documentNumberSequence, $pdo));
$commandBus->register(\App\Application\Invoice\Command\MarkInvoicePaid::class, new MarkInvoicePaidHandler($invoiceRepository));
// --- Onlinebanking-Import / Zahlungsabgleich (MT940/CAMT.053) ----------
$commandBus->register(\App\Application\BankImport\Command\ImportBankStatement::class, new ImportBankStatementHandler($invoiceRepository, $bankImportStaging));
$commandBus->register(\App\Application\BankImport\Command\ConfirmBankImport::class, new ConfirmBankImportHandler($invoiceRepository, $bankImportStaging));
$commandBus->register(\App\Application\BankImport\Command\CancelBankImport::class, new CancelBankImportHandler($bankImportStaging));
$commandBus->register(\App\Application\Invoice\Command\MarkInvoiceCancelled::class, new MarkInvoiceCancelledHandler($invoiceRepository));
$commandBus->register(\App\Application\Invoice\Command\CreateInvoiceFromOffer::class, new CreateInvoiceFromOfferHandler($offerRepository, $invoiceRepository, $saleRepository, $saleStockLotRepository, $saleStockConsumptionRepository, $inventoryNumberRepository, $articleRepository));
$commandBus->register(\App\Application\Invoice\Command\CreateInvoiceFromRental::class, new CreateInvoiceFromRentalHandler($rentalRepository, $rentalItemRepository, $articleRepository, $priceTierRepository, $invoiceRepository, $pricingSettingsRepository));
$invoicePdfGenerator = new InvoicePdfGenerator($fileStorage, $richTextPdfRenderer);
$xRechnungGenerator = new XRechnungGenerator();
$getInvoiceHandler = new GetInvoiceHandler($invoiceRepository, $customerRepository, $articleRepository, $laborTimeArticleRepository, $manufacturerRepository, $userRepository);
$commandBus->register(\App\Application\Invoice\Command\EmailInvoicePdf::class, new EmailInvoicePdfHandler(
    $getInvoiceHandler,
    $companySettingsRepository,
    $invoiceSettingsRepository,
    $legalSettingsRepository,
    $invoicePdfSettingsRepository,
    $pdfDesignSettingsRepository,
    $pdfLayoutSettingsRepository,
    $invoicePdfGenerator,
    $xRechnungGenerator,
    $mailer,
    $emailTemplateRenderer,
    $documentStoragePusher,
));

$commandBus->register(\App\Application\Invoice\Command\SendInvoiceSentViaMessaging::class, new \App\Application\Invoice\Command\SendInvoiceSentViaMessagingHandler(
    $getInvoiceHandler,
    $invoiceSettingsRepository,
    $messagingSettingsRepository,
    $messagingTemplateRenderer,
    $messageSender,
));
$commandBus->register(\App\Application\Invoice\Command\SendInvoiceDueReminderViaMessaging::class, new \App\Application\Invoice\Command\SendInvoiceDueReminderViaMessagingHandler(
    $getInvoiceHandler,
    $invoiceSettingsRepository,
    $messagingSettingsRepository,
    $messagingTemplateRenderer,
    $messageSender,
));
$commandBus->register(\App\Application\Invoice\Command\CreateInvoiceDueReminder::class, new \App\Application\Invoice\Command\CreateInvoiceDueReminderHandler($invoiceDueReminderRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateInvoiceDueReminderSettings::class, new \App\Application\Settings\Command\UpdateInvoiceDueReminderSettingsHandler($invoiceDueReminderSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetInvoiceDueReminderSettings::class, new \App\Application\Settings\Query\GetInvoiceDueReminderSettingsHandler($invoiceDueReminderSettingsRepository));

$commandBus->register(\App\Application\Settings\Command\UpdateInvoiceSettings::class, new UpdateInvoiceSettingsHandler($invoiceSettingsRepository));
$commandBus->register(\App\Application\Numbering\Command\UpdateDocumentNumberingSettings::class, new \App\Application\Numbering\Command\UpdateDocumentNumberingSettingsHandler($documentNumberingSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdatePricingSettings::class, new UpdatePricingSettingsHandler($pricingSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateOverageSettings::class, new \App\Application\Settings\Command\UpdateOverageSettingsHandler($overageSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetOverageSettings::class, new \App\Application\Settings\Query\GetOverageSettingsHandler($overageSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateInvoicePdfSettings::class, new UpdateInvoicePdfSettingsHandler($invoicePdfSettingsRepository, $htmlSanitizer));
$commandBus->register(\App\Application\Settings\Command\UpdatePdfDesignSettings::class, new \App\Application\Settings\Command\UpdatePdfDesignSettingsHandler($pdfDesignSettingsRepository));

// --- Mahnwesen (Dunning) -----------------------------------------------
$commandBus->register(\App\Application\Dunning\Command\CreateDunningLevel::class, new \App\Application\Dunning\Command\CreateDunningLevelHandler($dunningLevelRepository));
$commandBus->register(\App\Application\Dunning\Command\UpdateDunningLevel::class, new \App\Application\Dunning\Command\UpdateDunningLevelHandler($dunningLevelRepository));
$commandBus->register(\App\Application\Dunning\Command\DeleteDunningLevel::class, new \App\Application\Dunning\Command\DeleteDunningLevelHandler($dunningLevelRepository));
$commandBus->register(\App\Application\Dunning\Command\UpdateDunningSettings::class, new \App\Application\Dunning\Command\UpdateDunningSettingsHandler($dunningSettingsRepository));
$commandBus->register(\App\Application\Dunning\Command\CreateDunningNotice::class, new \App\Application\Dunning\Command\CreateDunningNoticeHandler(
    $getInvoiceHandler,
    $invoiceSettingsRepository,
    $dunningLevelRepository,
    $dunningNoticeRepository,
    $documentNumberSequence,
    $pdo,
));
$getDunningNoticeHandler = new \App\Application\Dunning\Query\GetDunningNoticeHandler($dunningNoticeRepository, $invoiceRepository, $customerRepository);
$commandBus->register(\App\Application\Dunning\Command\EmailDunningNotice::class, new \App\Application\Dunning\Command\EmailDunningNoticeHandler(
    $getDunningNoticeHandler,
    $companySettingsRepository,
    $pdfLayoutSettingsRepository,
    $dunningPdfGenerator,
    $mailer,
    $emailTemplateRenderer,
    $dunningNoticeRepository,
    $documentStoragePusher,
));
$commandBus->register(\App\Application\Dunning\Command\SendDunningNoticeViaMessaging::class, new \App\Application\Dunning\Command\SendDunningNoticeViaMessagingHandler(
    $getDunningNoticeHandler,
    $messagingSettingsRepository,
    $messagingTemplateRenderer,
    $messageSender,
));

// --- Belege (Receipts) -----------------------------------------------------
$commandBus->register(\App\Application\Receipt\Command\CreateReceipt::class, new CreateReceiptHandler($receiptRepository, $supplierRepository, $fileStorage, $documentStoragePusher));
$commandBus->register(\App\Application\Receipt\Command\UpdateReceipt::class, new UpdateReceiptHandler($receiptRepository, $supplierRepository, $fileStorage));
$commandBus->register(\App\Application\Receipt\Command\DeleteReceipt::class, new DeleteReceiptHandler($receiptRepository, $fileStorage, $inventoryNumberRepository));
$commandBus->register(\App\Application\Receipt\Command\MarkReceiptPaid::class, new MarkReceiptPaidHandler($receiptRepository));
$commandBus->register(\App\Application\Receipt\Command\MarkReceiptOpen::class, new MarkReceiptOpenHandler($receiptRepository));

// --- Beleg-Posteingang (E-Mail-Abruf) --------------------------------------
$commandBus->register(\App\Application\Settings\Command\UpdateMailboxSettings::class, new UpdateMailboxSettingsHandler($mailboxSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\TestMailboxConnection::class, new TestMailboxConnectionHandler($mailboxSettingsRepository));
$commandBus->register(\App\Application\ReceiptInbox\Command\AddAllowedSender::class, new AddAllowedSenderHandler($receiptInboxAllowedSenderRepository));
$commandBus->register(\App\Application\ReceiptInbox\Command\DeleteAllowedSender::class, new DeleteAllowedSenderHandler($receiptInboxAllowedSenderRepository));
$commandBus->register(\App\Application\ReceiptInbox\Command\ImportReceiptEmails::class, new ImportReceiptEmailsHandler(
    $mailboxSettingsRepository,
    $receiptInboxAllowedSenderRepository,
    $receiptInboxMessageRepository,
    $receiptInboxAttachmentRepository,
    $fileStorage,
    $userRepository,
    $mailer,
    $pushSettingsRepository,
    $pushSubscriptionRepository,
));

// --- Browser-Push-Benachrichtigungen (Web Push, zusätzlich zur obigen E-Mail) ---
$commandBus->register(\App\Application\Settings\Command\EnsureVapidKeysGenerated::class, new EnsureVapidKeysGeneratedHandler($pushSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdatePushSettings::class, new UpdatePushSettingsHandler($pushSettingsRepository));
$commandBus->register(\App\Application\Push\Command\SubscribeToPush::class, new SubscribeToPushHandler($pushSubscriptionRepository));
$commandBus->register(\App\Application\Push\Command\UnsubscribeFromPush::class, new UnsubscribeFromPushHandler($pushSubscriptionRepository));
$commandBus->register(\App\Application\ReceiptInbox\Command\ConvertReceiptInboxAttachmentToReceipt::class, new ConvertReceiptInboxAttachmentToReceiptHandler(
    $receiptInboxAttachmentRepository,
    $receiptRepository,
    $supplierRepository,
    $fileStorage,
    $documentStoragePusher,
));
$commandBus->register(\App\Application\User\Command\UpdateNotifyOnNewReceiptEmail::class, new UpdateNotifyOnNewReceiptEmailHandler($userRepository));

// --- Ziellaufwerke (externe Speicherziele für automatischen Dokument-Push) ---
$commandBus->register(\App\Application\DocumentStorage\Command\CreateStorageTarget::class, new \App\Application\DocumentStorage\Command\CreateStorageTargetHandler($documentStorageTargetRepository));
$commandBus->register(\App\Application\DocumentStorage\Command\UpdateStorageTarget::class, new \App\Application\DocumentStorage\Command\UpdateStorageTargetHandler($documentStorageTargetRepository));
$commandBus->register(\App\Application\DocumentStorage\Command\DeleteStorageTarget::class, new \App\Application\DocumentStorage\Command\DeleteStorageTargetHandler($documentStorageTargetRepository));
$commandBus->register(\App\Application\DocumentStorage\Command\TestStorageConnection::class, new \App\Application\DocumentStorage\Command\TestStorageConnectionHandler($documentStorageTargetRepository, $documentStorageClientFactory));
$commandBus->register(\App\Application\DocumentStorage\Command\CreateStorageAssignment::class, new \App\Application\DocumentStorage\Command\CreateStorageAssignmentHandler($documentStorageAssignmentRepository, $documentStorageTargetRepository));
$commandBus->register(\App\Application\DocumentStorage\Command\DeleteStorageAssignment::class, new \App\Application\DocumentStorage\Command\DeleteStorageAssignmentHandler($documentStorageAssignmentRepository));

$commandBus->register(\App\Application\Settings\Command\UpdateIntegrationBaseSettings::class, new \App\Application\Settings\Command\UpdateIntegrationBaseSettingsHandler($integrationBaseSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateGoogleMerchantSettings::class, new \App\Application\Settings\Command\UpdateGoogleMerchantSettingsHandler($googleMerchantSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateAiAdImageSettings::class, new \App\Application\Settings\Command\UpdateAiAdImageSettingsHandler($aiAdImageSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UploadGoogleMerchantServiceAccount::class, new \App\Application\Settings\Command\UploadGoogleMerchantServiceAccountHandler($googleMerchantSettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\DeleteGoogleMerchantServiceAccount::class, new \App\Application\Settings\Command\DeleteGoogleMerchantServiceAccountHandler($googleMerchantSettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\UpdateGoogleBusinessClientCredentials::class, new \App\Application\Settings\Command\UpdateGoogleBusinessClientCredentialsHandler($googleBusinessSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\CompleteGoogleBusinessOAuth::class, new \App\Application\Settings\Command\CompleteGoogleBusinessOAuthHandler($googleBusinessSettingsRepository, $googleBusinessApiClient));
$commandBus->register(\App\Application\Settings\Command\DisconnectGoogleBusiness::class, new \App\Application\Settings\Command\DisconnectGoogleBusinessHandler($googleBusinessSettingsRepository));
$commandBus->register(\App\Application\Settings\Command\UpdateWhatsappBusinessSettings::class, new \App\Application\Settings\Command\UpdateWhatsappBusinessSettingsHandler($whatsappBusinessSettingsRepository));

$commandBus->register(\App\Application\Settings\Command\UpdateApiSettings::class, new \App\Application\Settings\Command\UpdateApiSettingsHandler($apiSettingsRepository));
$commandBus->register(\App\Application\Api\Command\CreateApiToken::class, new \App\Application\Api\Command\CreateApiTokenHandler($apiTokenRepository));
$commandBus->register(\App\Application\Api\Command\UpdateApiToken::class, new \App\Application\Api\Command\UpdateApiTokenHandler($apiTokenRepository));
$commandBus->register(\App\Application\Api\Command\SetApiTokenEnabled::class, new \App\Application\Api\Command\SetApiTokenEnabledHandler($apiTokenRepository));
$commandBus->register(\App\Application\Api\Command\DeleteApiToken::class, new \App\Application\Api\Command\DeleteApiTokenHandler($apiTokenRepository));

$commandBus->register(\App\Application\Calendar\Command\CreateCalendar::class, new \App\Application\Calendar\Command\CreateCalendarHandler($calendarRepository));
$commandBus->register(\App\Application\Calendar\Command\UpdateCalendar::class, new \App\Application\Calendar\Command\UpdateCalendarHandler($calendarRepository));
$commandBus->register(\App\Application\Calendar\Command\SetCalendarEnabled::class, new \App\Application\Calendar\Command\SetCalendarEnabledHandler($calendarRepository));
$commandBus->register(\App\Application\Calendar\Command\DeleteCalendar::class, new \App\Application\Calendar\Command\DeleteCalendarHandler($calendarRepository));
$commandBus->register(\App\Application\Calendar\Command\SetCalendarAccess::class, new \App\Application\Calendar\Command\SetCalendarAccessHandler($calendarRepository, $calendarAccessRepository));
$commandBus->register(\App\Application\Calendar\Command\CreateCalDavCredential::class, new \App\Application\Calendar\Command\CreateCalDavCredentialHandler($calDavCredentialRepository));
$commandBus->register(\App\Application\Calendar\Command\DeleteCalDavCredential::class, new \App\Application\Calendar\Command\DeleteCalDavCredentialHandler($calDavCredentialRepository));
$commandBus->register(\App\Application\Settings\Command\UploadCalDavSigningCertificate::class, new \App\Application\Settings\Command\UploadCalDavSigningCertificateHandler($calDavSigningSettingsRepository, $fileStorage));
$commandBus->register(\App\Application\Settings\Command\DeleteCalDavSigningCertificate::class, new \App\Application\Settings\Command\DeleteCalDavSigningCertificateHandler($calDavSigningSettingsRepository, $fileStorage));
$commandBus->register(\App\Application\AddressBook\Command\CreateCardDavCredential::class, new \App\Application\AddressBook\Command\CreateCardDavCredentialHandler($cardDavCredentialRepository));
$commandBus->register(\App\Application\AddressBook\Command\DeleteCardDavCredential::class, new \App\Application\AddressBook\Command\DeleteCardDavCredentialHandler($cardDavCredentialRepository));

// --- Weitere Query-Registrierungen ($queryBus wurde bereits weiter oben, neben
// dem Command-Bus, instanziiert) ---------------------------------------------
$queryBus->register(\App\Application\Category\Query\ListCategories::class, new ListCategoriesHandler($categoryRepository));
$queryBus->register(\App\Application\ClosingTextTemplate\Query\ListClosingTextTemplateFolders::class, new \App\Application\ClosingTextTemplate\Query\ListClosingTextTemplateFoldersHandler($closingTextTemplateFolderRepository));
$queryBus->register(\App\Application\ClosingTextTemplate\Query\ListClosingTextTemplates::class, new \App\Application\ClosingTextTemplate\Query\ListClosingTextTemplatesHandler($closingTextTemplateRepository));
$queryBus->register(\App\Application\Manufacturer\Query\ListManufacturers::class, new ListManufacturersHandler($manufacturerRepository));
$queryBus->register(\App\Application\LaborTime\Query\ListLaborTimeArticles::class, new ListLaborTimeArticlesHandler($laborTimeArticleRepository));

$listArticlesHandler = new ListArticlesHandler($articleRepository, $categoryRepository);
$queryBus->register(\App\Application\Article\Query\ListArticles::class, $listArticlesHandler);
// Zentraler Use Case "Artikelliste mit aufgelöstem Hersteller/Kategorie" —
// genutzt von ArticleController (Frontend) und ArticleApiController (REST-API).
$queryBus->register(\App\Application\Article\Query\ListArticlesForDisplay::class, new \App\Application\Article\Query\ListArticlesForDisplayHandler(
    $listArticlesHandler,
    $manufacturerRepository,
    $categoryRepository,
));
$queryBus->register(\App\Application\Article\Query\GetArticle::class, new GetArticleHandler(
    $articleRepository,
    $manufacturerRepository,
    $categoryRepository,
    $inventoryNumberRepository,
    $rmaRepository,
    $articleAccessoryRepository,
    $articlePreIssueCheckRepository,
    $articleAdImageRepository,
    $noticeDocumentArticleRepository,
    $noticeDocumentRepository,
    $articleChannelListingRepository,
    $saleRepository,
    $firmwareVersionRepository,
));
$queryBus->register(\App\Application\Article\Query\GetInventoryNumberContext::class, new GetInventoryNumberContextHandler(
    $inventoryNumberRepository,
    $articleRepository,
    $manufacturerRepository,
    $firmwareVersionRepository,
));
$queryBus->register(\App\Application\Firmware\Query\ListFirmwareUpdatesAvailable::class, new \App\Application\Firmware\Query\ListFirmwareUpdatesAvailableHandler(
    $inventoryNumberRepository,
    $articleRepository,
    $manufacturerRepository,
    $firmwareVersionRepository,
));

$queryBus->register(\App\Application\ElectricalInspection\Query\ListElectricalInspectionsForInventoryNumber::class, new ListElectricalInspectionsForInventoryNumberHandler($electricalInspectionRepository));
$queryBus->register(\App\Application\ElectricalInspection\Query\GetElectricalInspection::class, new GetElectricalInspectionHandler($electricalInspectionRepository));

$queryBus->register(\App\Application\Rma\Query\ListRmasForInventoryNumber::class, new ListRmasForInventoryNumberHandler($rmaRepository, $rmaDocumentRepository, $rmaPackagePhotoRepository, $receiptRepository, $rmaReceiptRepository));
$queryBus->register(\App\Application\Rma\Query\GetRma::class, new GetRmaHandler($rmaRepository, $rmaDocumentRepository, $rmaPackagePhotoRepository, $receiptRepository, $rmaReceiptRepository));
$queryBus->register(\App\Application\Rma\Query\ListRmas::class, new ListRmasHandler(
    $rmaRepository,
    $rmaDocumentRepository,
    $inventoryNumberRepository,
    $articleRepository,
    $manufacturerRepository,
));

$queryBus->register(\App\Application\Customer\Query\ListCustomers::class, new ListCustomersHandler($customerRepository));
$queryBus->register(\App\Application\Customer\Query\GetCustomer::class, new GetCustomerHandler($customerRepository));

$queryBus->register(\App\Application\CaseItem\Query\ListCaseItems::class, new ListCaseItemsHandler($caseItemRepository));
$queryBus->register(\App\Application\CaseItem\Query\GetCaseItem::class, new GetCaseItemHandler(
    $caseItemRepository,
    $manufacturerRepository,
    $caseInventoryNumberRepository,
    $caseContentItemRepository,
    $articleRepository,
));

$queryBus->register(\App\Application\Supplier\Query\ListSuppliers::class, new ListSuppliersHandler($supplierRepository));
$queryBus->register(\App\Application\Supplier\Query\GetSupplier::class, new GetSupplierHandler($supplierRepository));

$queryBus->register(\App\Application\Vehicle\Query\ListVehicles::class, new \App\Application\Vehicle\Query\ListVehiclesHandler($vehicleRepository));
$queryBus->register(\App\Application\Vehicle\Query\GetVehicle::class, new \App\Application\Vehicle\Query\GetVehicleHandler($vehicleRepository));
$queryBus->register(\App\Application\Vehicle\Query\GetRentalLoadingList::class, new \App\Application\Vehicle\Query\GetRentalLoadingListHandler(
    $rentalRepository,
    $rentalIssuedItemRepository,
    $articleRepository,
    $inventoryNumberRepository,
    $vehicleRepository,
    $rentalVehicleLoadRepository,
    $rentalVehicleTowRepository,
    $manufacturerRepository,
));

$queryBus->register(\App\Application\Voucher\Query\ListVouchers::class, new \App\Application\Voucher\Query\ListVouchersHandler($voucherRepository));
$queryBus->register(\App\Application\Voucher\Query\GetVoucher::class, new \App\Application\Voucher\Query\GetVoucherHandler($voucherRepository));
$queryBus->register(\App\Application\Settings\Query\GetVoucherSettings::class, new \App\Application\Settings\Query\GetVoucherSettingsHandler($voucherSettingsRepository));

$queryBus->register(\App\Application\Venue\Query\ListVenues::class, new ListVenuesHandler($venueRepository));
$queryBus->register(\App\Application\Venue\Query\GetVenue::class, new GetVenueHandler($venueRepository));
$queryBus->register(\App\Application\Venue\Query\ListVenuePowerConnections::class, new ListVenuePowerConnectionsHandler($venuePowerConnectionRepository));
$queryBus->register(\App\Application\Venue\Query\GetVenuesNeedDistributorFlags::class, new GetVenuesNeedDistributorFlagsHandler($venuePowerConnectionRepository));
$queryBus->register(\App\Application\Warehouse\Query\ListWarehouses::class, new ListWarehousesHandler($warehouseRepository));
$queryBus->register(\App\Application\Warehouse\Query\GetWarehouse::class, new GetWarehouseHandler($warehouseRepository));

$queryBus->register(\App\Application\Rental\Query\ListRentals::class, new ListRentalsHandler($rentalRepository, $customerRepository));
$getRentalHandler = new GetRentalHandler(
    $rentalRepository,
    $customerRepository,
    $rentalItemRepository,
    $rentalIssuedItemRepository,
    $articleRepository,
    $inventoryNumberRepository,
    $articleAccessoryRepository,
    $articlePreIssueCheckRepository,
    $manufacturerRepository,
);
$queryBus->register(\App\Application\Rental\Query\GetRental::class, $getRentalHandler);

$queryBus->register(\App\Application\MinuteProgram\Query\GetMinuteProgramDetail::class, new \App\Application\MinuteProgram\Query\GetMinuteProgramDetailHandler($minuteProgramRepository, $minuteProgramDetailBuilder));
$queryBus->register(\App\Application\MinuteProgram\Query\ListMinuteProgramsForRental::class, new \App\Application\MinuteProgram\Query\ListMinuteProgramsForRentalHandler($minuteProgramRepository));
$queryBus->register(\App\Application\MinuteProgram\Query\ListMinuteProgramTemplates::class, new \App\Application\MinuteProgram\Query\ListMinuteProgramTemplatesHandler($minuteProgramTemplateRepository));
$queryBus->register(\App\Application\MinuteProgram\Query\GetMinuteProgramTemplateDetail::class, new \App\Application\MinuteProgram\Query\GetMinuteProgramTemplateDetailHandler($minuteProgramTemplateRepository, $minuteProgramTemplateItemRepository));
$queryBus->register(\App\Application\MinuteProgram\Query\ResolveMinuteProgramShareLink::class, new \App\Application\MinuteProgram\Query\ResolveMinuteProgramShareLinkHandler($minuteProgramShareLinkRepository, $minuteProgramRepository, $minuteProgramDetailBuilder));

$getNoticeDocumentsForArticleIdsHandler = new \App\Application\NoticeDocument\Query\GetNoticeDocumentsForArticleIdsHandler($noticeDocumentArticleRepository, $noticeDocumentRepository);
$queryBus->register(\App\Application\NoticeDocument\Query\GetNoticeDocumentsForArticleIds::class, $getNoticeDocumentsForArticleIdsHandler);
$queryBus->register(\App\Application\NoticeDocument\Query\ListNoticeDocuments::class, new \App\Application\NoticeDocument\Query\ListNoticeDocumentsHandler($noticeDocumentRepository));
$queryBus->register(\App\Application\NoticeDocument\Query\GetNoticeDocumentDetail::class, new \App\Application\NoticeDocument\Query\GetNoticeDocumentDetailHandler($noticeDocumentRepository, $noticeDocumentArticleRepository));
$commandBus->register(\App\Application\Rental\Command\SendNoticeDocumentsToCustomer::class, new \App\Application\Rental\Command\SendNoticeDocumentsToCustomerHandler($getRentalHandler, $getNoticeDocumentsForArticleIdsHandler, $fileStorage, $mailer));
$queryBus->register(\App\Application\Rental\Query\ListRentalsInRange::class, new ListRentalsInRangeHandler(
    $rentalRepository,
    $customerRepository,
    $rentalItemRepository,
    $rentalIssuedItemRepository,
    $articleRepository,
    $inventoryNumberRepository,
    $manufacturerRepository,
));
$queryBus->register(\App\Application\Rental\Query\ListRentalHistoryForInventoryNumber::class, new ListRentalHistoryForInventoryNumberHandler(
    $rentalIssuedItemRepository,
    $rentalRepository,
    $customerRepository,
));
$queryBus->register(\App\Application\Rental\Query\ListRentalsForArticle::class, new ListRentalsForArticleHandler(
    $rentalItemRepository,
    $rentalRepository,
    $customerRepository,
    $rentalIssuedItemRepository,
));
$queryBus->register(\App\Application\Rental\Query\GetArticleAvailability::class, new GetArticleAvailabilityHandler(
    $inventoryNumberRepository,
    $rentalItemRepository,
    $rentalRepository,
));
$queryBus->register(\App\Application\Rental\Query\ListRentalsForVenue::class, new ListRentalsForVenueHandler(
    $rentalRepository,
    $customerRepository,
));

$queryBus->register(\App\Application\Offer\Query\ListOffers::class, new ListOffersHandler($offerRepository, $customerRepository));
$queryBus->register(\App\Application\Offer\Query\GetOffer::class, $getOfferHandler);

$queryBus->register(\App\Application\Purchase\Query\ListPurchaseOrders::class, new \App\Application\Purchase\Query\ListPurchaseOrdersHandler($purchaseOrderRepository, $supplierRepository));
$queryBus->register(\App\Application\Purchase\Query\GetPurchaseOrder::class, new \App\Application\Purchase\Query\GetPurchaseOrderHandler($purchaseOrderRepository, $supplierRepository, $articleRepository, $manufacturerRepository));
$queryBus->register(\App\Application\Sale\Query\ListSales::class, new \App\Application\Sale\Query\ListSalesHandler($saleRepository, $articleRepository, $customerRepository, $manufacturerRepository));

$queryBus->register(\App\Application\Invoice\Query\ListInvoices::class, new ListInvoicesHandler($invoiceRepository, $customerRepository));
$queryBus->register(\App\Application\Invoice\Query\GetInvoice::class, $getInvoiceHandler);
$queryBus->register(\App\Application\Settings\Query\GetInvoiceSettings::class, new GetInvoiceSettingsHandler($invoiceSettingsRepository));
$queryBus->register(\App\Application\Numbering\Query\ListDocumentNumberingSettings::class, new \App\Application\Numbering\Query\ListDocumentNumberingSettingsHandler($documentNumberingSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetPricingSettings::class, new GetPricingSettingsHandler($pricingSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetInvoicePdfSettings::class, new GetInvoicePdfSettingsHandler($invoicePdfSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetPdfDesignSettings::class, new \App\Application\Settings\Query\GetPdfDesignSettingsHandler($pdfDesignSettingsRepository));

$queryBus->register(\App\Application\Dunning\Query\ListDunningLevels::class, new \App\Application\Dunning\Query\ListDunningLevelsHandler($dunningLevelRepository));
$queryBus->register(\App\Application\Dunning\Query\GetDunningSettings::class, new \App\Application\Dunning\Query\GetDunningSettingsHandler($dunningSettingsRepository));
$queryBus->register(\App\Application\Dunning\Query\ListDunningNoticesForInvoice::class, new \App\Application\Dunning\Query\ListDunningNoticesForInvoiceHandler($dunningNoticeRepository));
$queryBus->register(\App\Application\Dunning\Query\GetDunningNotice::class, $getDunningNoticeHandler);

$queryBus->register(\App\Application\Receipt\Query\ListReceipts::class, new ListReceiptsHandler($receiptRepository, $supplierRepository));
$queryBus->register(\App\Application\Receipt\Query\GetReceipt::class, new GetReceiptHandler($receiptRepository, $supplierRepository, $inventoryNumberRepository, $articleRepository, $manufacturerRepository));

$queryBus->register(\App\Application\Settings\Query\GetMailboxSettings::class, new GetMailboxSettingsHandler($mailboxSettingsRepository));
$queryBus->register(\App\Application\ReceiptInbox\Query\ListAllowedSenders::class, new ListAllowedSendersHandler($receiptInboxAllowedSenderRepository));
$queryBus->register(\App\Application\ReceiptInbox\Query\ListReceiptInboxMessages::class, new ListReceiptInboxMessagesHandler($receiptInboxMessageRepository, $receiptInboxAttachmentRepository));

$queryBus->register(\App\Application\Settings\Query\GetPushSettings::class, new GetPushSettingsHandler($pushSettingsRepository));
$queryBus->register(\App\Application\Push\Query\ListPushSubscriptionsForUser::class, new ListPushSubscriptionsForUserHandler($pushSubscriptionRepository));

$queryBus->register(\App\Application\User\Query\ListUsers::class, new ListUsersHandler($userRepository));
$queryBus->register(\App\Application\User\Query\GetUser::class, new GetUserHandler($userRepository));

$queryBus->register(\App\Application\Settings\Query\GetSmtpSettings::class, new GetSmtpSettingsHandler($smtpSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetPriceTiers::class, new GetPriceTiersHandler($priceTierRepository));
$queryBus->register(\App\Application\Settings\Query\GetCompanySettings::class, new GetCompanySettingsHandler($companySettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetLegalSettings::class, new GetLegalSettingsHandler($legalSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetOfferPdfSettings::class, new GetOfferPdfSettingsHandler($offerPdfSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetIntegrationBaseSettings::class, new \App\Application\Settings\Query\GetIntegrationBaseSettingsHandler($integrationBaseSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetGoogleMerchantSettings::class, new \App\Application\Settings\Query\GetGoogleMerchantSettingsHandler($googleMerchantSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetAiAdImageSettings::class, new \App\Application\Settings\Query\GetAiAdImageSettingsHandler($aiAdImageSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetGoogleBusinessSettings::class, new \App\Application\Settings\Query\GetGoogleBusinessSettingsHandler($googleBusinessSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetWhatsappBusinessSettings::class, new \App\Application\Settings\Query\GetWhatsappBusinessSettingsHandler($whatsappBusinessSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetApiSettings::class, new \App\Application\Settings\Query\GetApiSettingsHandler($apiSettingsRepository));
$queryBus->register(\App\Application\Api\Query\ListApiTokens::class, new \App\Application\Api\Query\ListApiTokensHandler($apiTokenRepository));

$queryBus->register(\App\Application\Calendar\Query\ListCalendars::class, new \App\Application\Calendar\Query\ListCalendarsHandler($calendarRepository));
$queryBus->register(\App\Application\Calendar\Query\GetCalendar::class, new \App\Application\Calendar\Query\GetCalendarHandler($calendarRepository));
$queryBus->register(\App\Application\Calendar\Query\ListUserIdsForCalendar::class, new \App\Application\Calendar\Query\ListUserIdsForCalendarHandler($calendarAccessRepository));
$queryBus->register(\App\Application\Calendar\Query\ListCalDavCredentialsForUser::class, new \App\Application\Calendar\Query\ListCalDavCredentialsForUserHandler($calDavCredentialRepository));
$queryBus->register(\App\Application\Calendar\Query\ListCalendarsForUser::class, new \App\Application\Calendar\Query\ListCalendarsForUserHandler($calendarRepository, $calendarAccessRepository, $userRepository));
$queryBus->register(\App\Application\Calendar\Query\ListRentalsForCalendar::class, new \App\Application\Calendar\Query\ListRentalsForCalendarHandler($calendarRepository, $rentalRepository, $categoryRepository));
$queryBus->register(\App\Application\Settings\Query\GetCalDavSigningSettings::class, new \App\Application\Settings\Query\GetCalDavSigningSettingsHandler($calDavSigningSettingsRepository));
$queryBus->register(\App\Application\AddressBook\Query\ListCardDavCredentialsForUser::class, new \App\Application\AddressBook\Query\ListCardDavCredentialsForUserHandler($cardDavCredentialRepository));
$queryBus->register(\App\Application\Reporting\Query\GetIncomeExpenseOverview::class, new \App\Application\Reporting\Query\GetIncomeExpenseOverviewHandler($invoiceRepository, $receiptRepository, $invoiceSettingsRepository));
$queryBus->register(\App\Application\Reporting\Query\GetRevenueForecast::class, new \App\Application\Reporting\Query\GetRevenueForecastHandler($offerRepository, $orderConfirmationRepository, $invoiceRepository, $invoiceSettingsRepository));

$queryBus->register(\App\Application\Printing\Query\ListLabelPrinters::class, new ListLabelPrintersHandler($labelPrinterRepository));

$queryBus->register(\App\Application\Auth\Query\ListPasskeysForUser::class, new ListPasskeysForUserHandler($webauthnCredentialRepository));
$queryBus->register(\App\Application\Auth\Query\ListSessionsForUser::class, new \App\Application\Auth\Query\ListSessionsForUserHandler($userSessionRepository));
$queryBus->register(\App\Application\Auth\Query\ListImpersonations::class, new \App\Application\Auth\Query\ListImpersonationsHandler($impersonationRepository, $userRepository));

$queryBus->register(\App\Application\DocumentStorage\Query\ListStorageTargets::class, new \App\Application\DocumentStorage\Query\ListStorageTargetsHandler($documentStorageTargetRepository));
$queryBus->register(\App\Application\DocumentStorage\Query\ListStorageAssignments::class, new \App\Application\DocumentStorage\Query\ListStorageAssignmentsHandler($documentStorageAssignmentRepository));
$queryBus->register(\App\Application\DocumentStorage\Query\ListStorageUploadLog::class, new \App\Application\DocumentStorage\Query\ListStorageUploadLogHandler($documentStorageUploadLogRepository));

// --- Controller --------------------------------------------------------
$dashboardController = new DashboardController($queryBus, $rentalRepository, $invoiceRepository, $customerRepository, $supplierRepository);
$reportingController = new \App\Infrastructure\Http\Controllers\ReportingController($queryBus);
$categoryController = new CategoryController($commandBus, $queryBus);
$closingTextTemplateController = new \App\Infrastructure\Http\Controllers\ClosingTextTemplateController($commandBus, $queryBus);
$manufacturerController = new ManufacturerController($commandBus, $queryBus);
$laborTimeArticleController = new LaborTimeArticleController($commandBus, $queryBus);
$articleController = new ArticleController($commandBus, $queryBus, $fileStorage, new PriceListPdfGenerator(), new PriceListCsvExporter(), $saleStockLotRepository, $customerRepository);
$rmaController = new RmaController($commandBus, $queryBus, $fileStorage);
$firmwareController = new \App\Infrastructure\Http\Controllers\FirmwareController($queryBus);
$electricalInspectionController = new ElectricalInspectionController($commandBus, $queryBus);
$customerController = new CustomerController($commandBus, $queryBus);
$caseController = new CaseController($commandBus, $queryBus);
$supplierController = new SupplierController($commandBus, $queryBus);
$purchaseOrderController = new \App\Infrastructure\Http\Controllers\PurchaseOrderController($commandBus, $queryBus, $supplierRepository);
$salesController = new \App\Infrastructure\Http\Controllers\SalesController($queryBus);
$vehicleController = new \App\Infrastructure\Http\Controllers\VehicleController($commandBus, $queryBus);
$venueController = new VenueController($commandBus, $queryBus);
$warehouseController = new WarehouseController($commandBus, $queryBus);
$packingListPdfGenerator = new PackingListPdfGenerator($fileStorage);
$commandBus->register(\App\Application\Rental\Command\EmailPackingListPdf::class, new EmailPackingListPdfHandler(
    $getRentalHandler,
    $companySettingsRepository,
    $pdfLayoutSettingsRepository,
    $packingListPdfGenerator,
    $mailer,
    $emailTemplateRenderer,
    $documentStoragePusher,
));

// --- Abhol-/Rückgabe-Erinnerungen (siehe bin/send-rental-reminders.php) -
$commandBus->register(\App\Application\Rental\Command\CreateRentalReminder::class, new \App\Application\Rental\Command\CreateRentalReminderHandler($rentalReminderRepository));
$commandBus->register(\App\Application\Rental\Command\EmailRentalPickupReminder::class, new \App\Application\Rental\Command\EmailRentalPickupReminderHandler(
    $getRentalHandler,
    $warehouseRepository,
    $mailer,
    $emailTemplateRenderer,
));
$commandBus->register(\App\Application\Rental\Command\EmailRentalReturnReminder::class, new \App\Application\Rental\Command\EmailRentalReturnReminderHandler(
    $getRentalHandler,
    $warehouseRepository,
    $mailer,
    $emailTemplateRenderer,
));
$commandBus->register(\App\Application\Settings\Command\UpdateRentalReminderSettings::class, new \App\Application\Settings\Command\UpdateRentalReminderSettingsHandler($rentalReminderSettingsRepository));
$queryBus->register(\App\Application\Settings\Query\GetRentalReminderSettings::class, new \App\Application\Settings\Query\GetRentalReminderSettingsHandler($rentalReminderSettingsRepository));
$commandBus->register(\App\Application\Rental\Command\SendRentalReminderViaMessaging::class, new \App\Application\Rental\Command\SendRentalReminderViaMessagingHandler(
    $getRentalHandler,
    $warehouseRepository,
    $messagingSettingsRepository,
    $messagingTemplateRenderer,
    $messageSender,
));

// --- Messenger-Benachrichtigungen (WhatsApp Business/Telegram/Signal, siehe App\Domain\Messaging) -
$commandBus->register(\App\Application\Messaging\Command\UpdateMessagingSettings::class, new \App\Application\Messaging\Command\UpdateMessagingSettingsHandler($messagingSettingsRepository));
$queryBus->register(\App\Application\Messaging\Query\GetMessagingSettings::class, new \App\Application\Messaging\Query\GetMessagingSettingsHandler($messagingSettingsRepository));
$commandBus->register(\App\Application\Messaging\Command\TestMessagingConnection::class, new \App\Application\Messaging\Command\TestMessagingConnectionHandler($messageSender));
$queryBus->register(\App\Application\Messaging\Query\ListMessageTemplates::class, new \App\Application\Messaging\Query\ListMessageTemplatesHandler($messageTemplateRepository));
$queryBus->register(\App\Application\Messaging\Query\GetMessageTemplate::class, new \App\Application\Messaging\Query\GetMessageTemplateHandler($messageTemplateRepository));
$commandBus->register(\App\Application\Messaging\Command\SaveMessageTemplate::class, new \App\Application\Messaging\Command\SaveMessageTemplateHandler($messageTemplateRepository));
$commandBus->register(\App\Application\Messaging\Command\ResetMessageTemplate::class, new \App\Application\Messaging\Command\ResetMessageTemplateHandler($messageTemplateRepository));
$commandBus->register(\App\Application\Messaging\Command\SendAdHocMessageToCustomer::class, new \App\Application\Messaging\Command\SendAdHocMessageToCustomerHandler(
    $customerRepository,
    $messagingSettingsRepository,
    $messagingTemplateRenderer,
    $messageSender,
));

// --- Kunden-Checkliste (siehe App\Domain\Rental\RentalCustomerChecklistToken) -
$commandBus->register(\App\Application\Rental\Command\IssueRentalCustomerChecklistToken::class, new \App\Application\Rental\Command\IssueRentalCustomerChecklistTokenHandler($rentalRepository, $rentalCustomerChecklistTokenIssuer));
$commandBus->register(\App\Application\Rental\Command\ConfirmRentalItemByCustomer::class, new \App\Application\Rental\Command\ConfirmRentalItemByCustomerHandler($rentalCustomerChecklistTokenRepository, $rentalIssuedItemRepository));
$commandBus->register(\App\Application\Rental\Command\EmailRentalCustomerChecklist::class, new \App\Application\Rental\Command\EmailRentalCustomerChecklistHandler(
    $getRentalHandler,
    $rentalCustomerChecklistTokenIssuer,
    $rentalCustomerChecklistUrlBuilder,
    $mailer,
    $emailTemplateRenderer,
));
$queryBus->register(\App\Application\Rental\Query\GetRentalCustomerChecklist::class, new \App\Application\Rental\Query\GetRentalCustomerChecklistHandler(
    $rentalCustomerChecklistTokenRepository,
    $rentalRepository,
    $customerRepository,
    $rentalIssuedItemRepository,
    $articleRepository,
    $inventoryNumberRepository,
    $manufacturerRepository,
));
$customerChecklistController = new \App\Infrastructure\Http\Controllers\CustomerChecklistController($queryBus, $commandBus);

// --- Backup-Export/-Import -----------------------------------------------
$commandBus->register(\App\Application\Backup\Command\ExportBackup::class, new \App\Application\Backup\Command\ExportBackupHandler($backupArchiveBuilder));
$commandBus->register(\App\Application\Backup\Command\ImportBackup::class, new \App\Application\Backup\Command\ImportBackupHandler($backupArchiveImporter));
$backupSettingsController = new \App\Infrastructure\Http\Controllers\BackupSettingsController($commandBus, $backupTempDirectory);

$rentalController = new RentalController($commandBus, $queryBus, $inventoryNumberRepository, $rentalIssuedItemRepository, $venueRepository, $warehouseRepository, $packingListPdfGenerator, $rentalCustomerChecklistTokenRepository, $rentalCustomerChecklistUrlBuilder, $voucherRedemptionRepository, $legalSettingsRepository, $rentalRepository);
$minuteProgramController = new \App\Infrastructure\Http\Controllers\MinuteProgramController($commandBus, $queryBus, $minuteProgramShareLinkRepository);
$minuteProgramTemplateController = new \App\Infrastructure\Http\Controllers\MinuteProgramTemplateController($commandBus, $queryBus);
$minuteProgramShareController = new \App\Infrastructure\Http\Controllers\MinuteProgramShareController($queryBus, $commandBus);
$noticeDocumentController = new \App\Infrastructure\Http\Controllers\NoticeDocumentController($commandBus, $queryBus, $fileStorage);
$voucherController = new VoucherController($commandBus, $queryBus, $voucherRedemptionRepository, $voucherRepository);
$voucherSettingsController = new VoucherSettingsController($commandBus, $queryBus);
$offerController = new OfferController($commandBus, $queryBus, $venueRepository, $offerPdfGenerator, $inventoryNumberRepository, $saleStockLotRepository, $userRepository);
$orderConfirmationController = new OrderConfirmationController($commandBus, $queryBus, $venueRepository, $orderConfirmationPdfGenerator);
$invoiceController = new InvoiceController($commandBus, $queryBus, $venueRepository, $invoicePdfGenerator, $xRechnungGenerator, $rentalRepository, $customerRepository, $userRepository);
$bankImportController = new BankImportController($commandBus, $queryBus, $invoiceRepository);
$invoiceSettingsController = new InvoiceSettingsController($commandBus, $queryBus);
$numberingSettingsController = new DocumentNumberingSettingsController($commandBus, $queryBus);
$dunningLevelController = new \App\Infrastructure\Http\Controllers\DunningLevelController($commandBus, $queryBus);
$dunningController = new \App\Infrastructure\Http\Controllers\DunningController($commandBus, $queryBus, $dunningPdfGenerator);
$pricingSettingsController = new PricingSettingsController($commandBus, $queryBus);
$invoicePdfTemplateSettingsController = new InvoicePdfTemplateSettingsController($commandBus, $queryBus);
$receiptController = new ReceiptController($commandBus, $queryBus, $fileStorage);
$mailboxSettingsController = new MailboxSettingsController($commandBus, $queryBus);
$pushSettingsController = new PushSettingsController($commandBus, $queryBus);
$messagingSettingsController = new \App\Infrastructure\Http\Controllers\MessagingSettingsController($commandBus, $queryBus);
$aiAdImageSettingsController = new \App\Infrastructure\Http\Controllers\AiAdImageSettingsController($commandBus, $queryBus);
$messageTemplateSettingsController = new \App\Infrastructure\Http\Controllers\MessageTemplateSettingsController($commandBus, $queryBus);
$receiptInboxController = new ReceiptInboxController($commandBus, $queryBus, $receiptInboxAttachmentRepository, $fileStorage);
$calendarController = new CalendarController($queryBus);
$inventoryNumberController = new InventoryNumberController($queryBus, $commandBus, $supplierRepository, $rmaRepository, $electricalInspectionRepository, $customerRepository, $saleRepository);
$labelPrinterController = new LabelPrinterController($commandBus, $queryBus);
$documentStorageSettingsController = new \App\Infrastructure\Http\Controllers\DocumentStorageSettingsController($commandBus, $queryBus);
$authController = new AuthController($commandBus, $userRepository, $userSessionRepository, $recordUserSession);
$setupPasswordController = new SetupPasswordController($commandBus, $passwordSetupTokenRepository, $recordUserSession);
$passkeyController = new PasskeyController($commandBus, $queryBus, $recordUserSession);
$calDavSigningCertificateProvider = new \App\Infrastructure\Http\CalDav\CalDavSigningCertificateProvider($calDavSigningSettingsRepository, $fileStorage, $companySettingsRepository);
$mobileConfigBuilder = new \App\Infrastructure\Http\CalDav\MobileConfigBuilder($calDavSigningCertificateProvider);
$profileController = new ProfileController($commandBus, $queryBus, $mobileConfigBuilder);
$userController = new UserController($commandBus, $queryBus);
$smtpSettingsController = new SmtpSettingsController($commandBus, $queryBus);
$priceTierSettingsController = new PriceTierSettingsController($commandBus, $queryBus);
$companySettingsController = new CompanySettingsController($commandBus, $queryBus, $fileStorage);
$integrationSettingsController = new \App\Infrastructure\Http\Controllers\IntegrationSettingsController($commandBus, $queryBus, $googleBusinessApiClient);
$apiSettingsController = new \App\Infrastructure\Http\Controllers\ApiSettingsController($commandBus, $queryBus);
$apiTokenGuard = new \App\Infrastructure\Http\Auth\ApiTokenGuard($apiSettingsRepository, $apiTokenRepository);
$articleApiController = new \App\Infrastructure\Http\Controllers\Api\ArticleApiController($queryBus, $fileStorage);
$calendarSettingsController = new \App\Infrastructure\Http\Controllers\CalendarSettingsController($commandBus, $queryBus);
$calDavAuthGuard = new \App\Infrastructure\Http\Auth\CalDavAuthGuard($userRepository, $calDavCredentialRepository);
$calDavController = new \App\Infrastructure\Http\CalDav\CalDavController($calDavAuthGuard, $queryBus, $calendarAccessRepository);
$cardDavAuthGuard = new \App\Infrastructure\Http\Auth\CardDavAuthGuard($userRepository, $cardDavCredentialRepository);
$cardDavController = new \App\Infrastructure\Http\CardDav\CardDavController($cardDavAuthGuard, $queryBus);
$publicProductController = new \App\Infrastructure\Http\Controllers\PublicProductController($queryBus, $fileStorage);
$emailTemplateSettingsController = new EmailTemplateSettingsController($commandBus, $queryBus, $emailTemplateRepository);
$legalSettingsController = new LegalSettingsController($commandBus, $queryBus);
$offerPdfTemplateSettingsController = new OfferPdfTemplateSettingsController($commandBus, $queryBus);
$pdfDesignSettingsController = new \App\Infrastructure\Http\Controllers\PdfDesignSettingsController($commandBus, $queryBus);
$pdfLayoutSettingsController = new \App\Infrastructure\Http\Controllers\PdfLayoutSettingsController($commandBus, $queryBus);
$rentalReminderSettingsController = new \App\Infrastructure\Http\Controllers\RentalReminderSettingsController($commandBus, $queryBus);
$invoiceDueReminderSettingsController = new \App\Infrastructure\Http\Controllers\InvoiceDueReminderSettingsController($commandBus, $queryBus);
$overageSettingsController = new \App\Infrastructure\Http\Controllers\OverageSettingsController($commandBus, $queryBus);
$legalPageController = new LegalPageController($queryBus);
$localeController = new LocaleController($commandBus, $userRepository);

// Einmal pro Request geladen, für die Impressum-/Datenschutz-Links im
// Footer (siehe View::page()/layout.php) — analog zum aktuellen Benutzer.
\App\Infrastructure\Http\LegalFooterLinks::set($queryBus->ask(new \App\Application\Settings\Query\GetLegalSettings()));

// Einmal pro Request geladen, damit Header, Login- und Zwei-Faktor-Seite das
// Firmenlogo anzeigen können (fällt auf das "M01"-Kürzel zurück, solange
// keins hinterlegt ist) — siehe CompanyBranding, layout.php, auth/login.php,
// auth/two-factor.php.
\App\Infrastructure\Http\CompanyBranding::set($queryBus->ask(new \App\Application\Settings\Query\GetCompanySettings()));

// Leichtgewichtiger, request-gebundener Helfer für das Benachrichtigungs-
// Badge im Menüband (siehe layout.php) — bewusst NICHT über den
// Query-/Command-Bus, sondern eine einzelne, gecachte COUNT(*)-Abfrage nur
// bei tatsächlichem Zugriff (siehe ReceiptInboxBadge::pendingCount()).
ReceiptInboxBadge::initialize($receiptInboxAttachmentRepository);

// --- Zugriffsschutz --------------------------------------------------------
//
// Jede Route (außer den öffentlichen Auth-Routen) wird über AuthGuard::guard()
// registriert: null verlangt nur eine gültige Anmeldung, ein Permission-Wert
// zusätzlich das jeweilige Recht (Administration schließt automatisch alle
// anderen Rechte ein, siehe App\Domain\User\User::has()). Die Zuordnung
// Programmbereich -> Recht ist eine Annahme (nicht im Detail vom Nutzer
// bestätigt, siehe Projektdokumentation):
//   Artikelverwaltung   = Artikel, Cases, Hersteller, Kategorien, Lieferanten, RMA
//   Kundenverwaltung    = Kunden
//   Ausgabe/Rücknahme   = Vermietungen, Ausgabe-/Rückgabe-Checkliste, Kalender
//   Administration      = zusätzlich Benutzer- und SMTP-Verwaltung
$auth = new AuthGuard($userRepository, $userSessionRepository);

// --- Routen --------------------------------------------------------------
$router = new Router();

// Öffentliche Auth-Routen (bewusst ungeschützt, siehe AuthGuard::PUBLIC_PATHS).
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->get('/login/zwei-faktor', [$authController, 'showTwoFactor']);
$router->post('/login/zwei-faktor', [$authController, 'verifyTwoFactor']);
$router->post('/login/zwei-faktor/code-erneut-senden', [$authController, 'resendEmailCode']);
$router->post('/logout', [$authController, 'logout']);

// Passwort vergessen (öffentlich, siehe AuthController::forgotPassword()
// für den User-Enumeration-Schutz) und "Passwort festlegen" (Admin-
// Einladung ohne Erstpasswort UND "Passwort vergessen" münden hier, siehe
// SetupPasswordController).
$router->get('/passwort-vergessen', [$authController, 'showForgotPassword']);
$router->post('/passwort-vergessen', [$authController, 'forgotPassword']);
$router->get('/passwort-einrichten/{token}', [$setupPasswordController, 'show']);
$router->post('/passwort-einrichten/{token}', [$setupPasswordController, 'submit']);

// Sprachumschalter (siehe layout.php-Kopfzeile): bewusst ungeschützt/ohne
// Anmeldung erreichbar, da auch die Login-Seite umschaltbar sein soll —
// für angemeldete Benutzer wird zusätzlich die dauerhafte Präferenz in der
// Datenbank aktualisiert (siehe LocaleController::switch()).
$router->post('/sprache', [$localeController, 'switch']);

// Firmenlogo (siehe CompanyBranding): bewusst ungeschützt/ohne Anmeldung
// erreichbar, da es im Header jeder Seite sowie auf der Login-/Zwei-Faktor-
// Seite angezeigt wird — die reine Bild-Auslieferung ist nicht schutzwürdig
// (Bearbeiten bleibt über /einstellungen/firma/logo weiterhin admin-
// geschützt, siehe CompanySettingsController::showLogo()).
$router->get('/firmenlogo', [$companySettingsController, 'showLogo']);
$router->get('/firmenlogo-dark', [$companySettingsController, 'showDarkLogo']);
$router->get('/firmenhintergrund', [$companySettingsController, 'showBackgroundImage']);

// Passkey-Anmeldung (öffentlich, kein Benutzer angemeldet — siehe
// AuthGuard::PUBLIC_PATHS, das ist der Sinn dieser beiden Routen).
$router->post('/passkeys/login/options', [$passkeyController, 'loginOptions']);
$router->post('/passkeys/login/complete', [$passkeyController, 'loginComplete']);

// Selbstbedienung: nur Anmeldung nötig, kein spezifisches Recht.
$router->get('/profil/passwort', $auth->guard(null, [$profileController, 'showPasswordForm']));
$router->post('/profil/passwort', $auth->guard(null, [$profileController, 'changePassword']));
$router->get('/profil/sicherheit', $auth->guard(null, [$profileController, 'showSecurity']));
$router->post('/profil/sicherheit/totp', $auth->guard(null, [$profileController, 'enableTotp']));
$router->post('/profil/sicherheit/email', $auth->guard(null, [$profileController, 'enableEmailOtp']));
$router->post('/profil/sicherheit/deaktivieren', $auth->guard(null, [$profileController, 'disableTwoFactor']));
$router->get('/profil/darstellung', $auth->guard(null, [$profileController, 'showDisplaySettings']));
$router->post('/profil/darstellung', $auth->guard(null, [$profileController, 'updateDisplaySettings']));
$router->get('/profil/dashboard', $auth->guard(null, [$profileController, 'showDashboardSettings']));
$router->post('/profil/dashboard', $auth->guard(null, [$profileController, 'updateDashboardSettings']));
$router->post('/profil/push-abo', $auth->guard(null, [$profileController, 'storePushSubscription']));
$router->post('/profil/push-abo/{id}/loeschen', $auth->guard(null, [$profileController, 'deletePushSubscription']));

// Aktive Sitzungen (Selbstbedienung, nur eigene Sitzungen — siehe
// Ownership-Prüfung in RevokeSessionHandler).
$router->get('/profil/sitzungen', $auth->guard(null, [$profileController, 'showSessions']));
$router->post('/profil/sitzungen/{id}/beenden', $auth->guard(null, [$profileController, 'revokeSession']));
$router->post('/profil/sitzungen/andere-beenden', $auth->guard(null, [$profileController, 'revokeOtherSessions']));

// CalDAV-App-Passwörter (Selbstverwaltung, jeder angemeldete User).
$router->get('/profil/caldav', $auth->guard(null, [$profileController, 'showCalDav']));
$router->post('/profil/caldav', $auth->guard(null, [$profileController, 'createCalDavCredential']));
$router->post('/profil/caldav/{id}/loeschen', $auth->guard(null, [$profileController, 'deleteCalDavCredential']));
$router->post('/profil/caldav/profil.mobileconfig', $auth->guard(null, [$profileController, 'downloadMobileConfig']));
$router->get('/profil/carddav', $auth->guard(null, [$profileController, 'showCardDav']));
$router->post('/profil/carddav', $auth->guard(null, [$profileController, 'createCardDavCredential']));
$router->post('/profil/carddav/{id}/loeschen', $auth->guard(null, [$profileController, 'deleteCardDavCredential']));

// Passkey-Verwaltung (Selbstbedienung, nur für den eigenen Account — siehe
// Ownership-Prüfung in DeletePasskeyHandler).
$router->post('/passkeys/register/options', $auth->guard(null, [$passkeyController, 'registerOptions']));
$router->post('/passkeys/register/complete', $auth->guard(null, [$passkeyController, 'registerComplete']));
$router->get('/passkeys', $auth->guard(null, [$passkeyController, 'list']));
$router->post('/passkeys/{id}/loeschen', $auth->guard(null, [$passkeyController, 'delete']));

$router->get('/', $auth->guard(null, [$dashboardController, 'index']));

$router->get('/kategorien', $auth->guard(Permission::Artikelverwaltung, [$categoryController, 'index']));
$router->get('/kategorien/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$categoryController, 'edit']));
$router->post('/kategorien', $auth->guard(Permission::Artikelverwaltung, [$categoryController, 'store']));
$router->post('/kategorien/{id}', $auth->guard(Permission::Artikelverwaltung, [$categoryController, 'update']));
$router->post('/kategorien/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$categoryController, 'destroy']));

// Textvorlagen für Schlusstexte (siehe App\Domain\ClosingTextTemplate):
// Verwaltung (Ordner/Vorlagen anlegen/bearbeiten/löschen) unter
// Administration wie die übrigen /einstellungen/...-Seiten. list() ist
// bewusst NICHT admin-gated (Permission::null wie /kunden/suche & Co.) —
// der "Vorlage einfügen"-Picker in Rechnung/Angebot/Auftragsbestätigung
// muss für jeden angemeldeten Benutzer funktionieren, unabhängig von
// dessen Administration-Recht.
$router->get('/textvorlagen/liste', $auth->guard(null, [$closingTextTemplateController, 'list']));
$router->get('/einstellungen/textvorlagen', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'index']));
$router->get('/einstellungen/textvorlagen/ordner/{id}/bearbeiten', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'editFolder']));
$router->post('/einstellungen/textvorlagen/ordner', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'storeFolder']));
$router->post('/einstellungen/textvorlagen/ordner/{id}', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'updateFolder']));
$router->post('/einstellungen/textvorlagen/ordner/{id}/loeschen', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'destroyFolder']));
$router->get('/einstellungen/textvorlagen/vorlagen/{id}/bearbeiten', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'editTemplate']));
$router->post('/einstellungen/textvorlagen/vorlagen', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'storeTemplate']));
$router->post('/einstellungen/textvorlagen/vorlagen/{id}', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'updateTemplate']));
$router->post('/einstellungen/textvorlagen/vorlagen/{id}/loeschen', $auth->guard(Permission::Administration, [$closingTextTemplateController, 'destroyTemplate']));

$router->get('/hersteller', $auth->guard(Permission::Artikelverwaltung, [$manufacturerController, 'index']));
// Live-Suche (JSON) für das Hersteller-Auswahlfeld in Artikel-/Case-
// Formularen: bewusst `null` statt Permission::Artikelverwaltung, da jeder
// angemeldete Benutzer, der einen Artikel/Case mit Herstellerauswahl
// anlegen darf, suchen können muss, unabhängig von dieser spezifischeren
// Berechtigung (identisch zum bisherigen, ungefilterten Dropdown).
$router->get('/hersteller/suche', $auth->guard(null, [$manufacturerController, 'search']));
$router->get('/hersteller/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$manufacturerController, 'edit']));
$router->post('/hersteller', $auth->guard(Permission::Artikelverwaltung, [$manufacturerController, 'store']));
$router->post('/hersteller/{id}', $auth->guard(Permission::Artikelverwaltung, [$manufacturerController, 'update']));
$router->post('/hersteller/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$manufacturerController, 'destroy']));
$router->get('/arbeitszeit-artikel', $auth->guard(Permission::Artikelverwaltung, [$laborTimeArticleController, 'index']));
$router->get('/arbeitszeit-artikel/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$laborTimeArticleController, 'edit']));
$router->post('/arbeitszeit-artikel', $auth->guard(Permission::Artikelverwaltung, [$laborTimeArticleController, 'store']));
$router->post('/arbeitszeit-artikel/{id}', $auth->guard(Permission::Artikelverwaltung, [$laborTimeArticleController, 'update']));
$router->post('/arbeitszeit-artikel/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$laborTimeArticleController, 'destroy']));

$router->get('/artikel', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'index']));
// Live-Suche (JSON) für die Artikelauswahl in Angebots-/Rechnungs-/
// Auftragsbestätigungsformularen — `null`-Permission, siehe Begründung bei
// '/hersteller/suche' oben.
$router->get('/artikel/suche', $auth->guard(null, [$articleController, 'search']));
$router->get('/artikel/neu', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'create']));
$router->post('/artikel', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'store']));
$router->get('/artikel/preisliste.pdf', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'exportPriceListPdf']));
$router->get('/artikel/preisliste.csv', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'exportPriceListCsv']));
$router->get('/artikel/{id}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'show']));
$router->get('/artikel/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'edit']));
$router->post('/artikel/{id}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'update']));
$router->post('/artikel/{id}/verkaufsbestand-verkaufen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'sellFromStock']));
$router->post('/artikel/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'destroy']));
$router->post('/artikel/{id}/aktivieren', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'activate']));
$router->post('/artikel/{id}/deaktivieren', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deactivate']));
$router->post('/artikel/{id}/bilder', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'uploadImage']));
$router->post('/artikelbilder/{imageId}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deleteImage']));
$router->get('/artikel-bilder/{id}/{filename}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'showImage']));

$router->post('/artikel/{id}/kanaele/{channel}/aktivieren', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'enableChannel']));
$router->post('/artikel/{id}/kanaele/{channel}/deaktivieren', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'disableChannel']));
$router->post('/artikel/{id}/kanaele/{channel}/synchronisieren', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'syncChannel']));

$router->post('/artikel/{id}/inventurnummern', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'addInventoryNumber']));
$router->get('/inventurnummern/{id}', $auth->guard(Permission::Artikelverwaltung, [$inventoryNumberController, 'show']));
$router->post('/inventurnummern/{id}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'updateInventoryNumber']));
$router->post('/inventurnummern/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deleteInventoryNumber']));
$router->post('/inventurnummern/{id}/etikett-drucken', $auth->guard(Permission::Artikelverwaltung, [$inventoryNumberController, 'printLabel']));
$router->post('/inventurnummern/{id}/verkaufen', $auth->guard(Permission::Artikelverwaltung, [$inventoryNumberController, 'sell']));

$router->post('/artikel/{id}/firmware-versionen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'addFirmwareVersion']));
$router->post('/firmware-versionen/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deleteFirmwareVersion']));

$router->post('/artikel/{id}/zubehoer', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'addAccessory']));
$router->post('/zubehoer/{id}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'updateAccessory']));
$router->post('/zubehoer/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deleteAccessory']));
$router->post('/artikel/{id}/vor-ausgabe-checks', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'addPreIssueCheck']));
$router->post('/vor-ausgabe-checks/{id}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'updatePreIssueCheck']));
$router->post('/vor-ausgabe-checks/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deletePreIssueCheck']));
$router->post('/artikel/{id}/werbebilder', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'generateAdImage']));
$router->post('/werbebilder/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'deleteAdImage']));
$router->get('/artikel-werbebilder/{id}/{filename}', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'showAdImage']));
$router->post('/artikel/{id}/hinweisdokumente', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'uploadNoticeDocument']));
$router->post('/artikel/{id}/hinweisdokumente/{documentId}/entfernen', $auth->guard(Permission::Artikelverwaltung, [$articleController, 'removeNoticeDocument']));

$router->get('/firmware', $auth->guard(Permission::Artikelverwaltung, [$firmwareController, 'overview']));

// Hinweisdokumente (generische, Artikeln zuordenbare Dokumente), siehe App\Domain\NoticeDocument.
$router->get('/hinweisdokumente', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'index']));
$router->get('/hinweisdokumente/neu', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'create']));
$router->post('/hinweisdokumente', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'store']));
$router->get('/hinweisdokumente/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'edit']));
$router->post('/hinweisdokumente/{id}', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'update']));
$router->post('/hinweisdokumente/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$noticeDocumentController, 'destroy']));
$router->get('/hinweisdokumente/{id}/herunterladen', $auth->guard(null, [$noticeDocumentController, 'download']));

$router->get('/rma', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'overview']));
$router->get('/inventurnummern/{inventoryNumberId}/rma', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'index']));
$router->post('/inventurnummern/{inventoryNumberId}/rma', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'store']));
$router->get('/inventurnummern/{inventoryNumberId}/rma/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'edit']));
$router->post('/inventurnummern/{inventoryNumberId}/rma/{id}', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'update']));
$router->post('/inventurnummern/{inventoryNumberId}/rma/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'destroy']));
$router->post('/inventurnummern/{inventoryNumberId}/rma/{id}/dokumente', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'uploadDocument']));
$router->get('/inventurnummern/{inventoryNumberId}/rma/{id}/dokumente/{documentId}', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'downloadDocument']));
$router->post('/inventurnummern/{inventoryNumberId}/rma-dokumente/{documentId}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'deleteDocument']));
$router->post('/inventurnummern/{inventoryNumberId}/rma/{id}/versandfotos', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'uploadPackagePhoto']));
$router->get('/inventurnummern/{inventoryNumberId}/rma/{id}/versandfotos/{photoId}', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'viewPackagePhoto']));
$router->post('/inventurnummern/{inventoryNumberId}/rma-versandfotos/{photoId}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$rmaController, 'deletePackagePhoto']));

$router->get('/inventurnummern/{inventoryNumberId}/elektropruefungen', $auth->guard(Permission::Artikelverwaltung, [$electricalInspectionController, 'index']));
$router->post('/inventurnummern/{inventoryNumberId}/elektropruefungen', $auth->guard(Permission::Artikelverwaltung, [$electricalInspectionController, 'store']));
$router->get('/inventurnummern/{inventoryNumberId}/elektropruefungen/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$electricalInspectionController, 'edit']));
$router->post('/inventurnummern/{inventoryNumberId}/elektropruefungen/{id}', $auth->guard(Permission::Artikelverwaltung, [$electricalInspectionController, 'update']));
$router->post('/inventurnummern/{inventoryNumberId}/elektropruefungen/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$electricalInspectionController, 'destroy']));

$router->get('/kunden', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'index']));
// Live-Suche (JSON) für die Kundenauswahl in Angebots-/Rechnungs-/
// Auftragsbestätigungs-/Vermietungsformularen — `null`-Permission, siehe
// Begründung bei '/hersteller/suche' oben.
$router->get('/kunden/suche', $auth->guard(null, [$customerController, 'search']));
$router->get('/kunden/neu', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'create']));
$router->post('/kunden', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'store']));
$router->get('/kunden/import', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'importForm']));
$router->get('/kunden/import/vorlage', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'importTemplate']));
$router->post('/kunden/import', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'import']));
$router->get('/kunden/{id}/bearbeiten', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'edit']));
$router->post('/kunden/{id}', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'update']));
$router->post('/kunden/{id}/loeschen', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'destroy']));
$router->post('/kunden/{id}/nachricht', $auth->guard(Permission::Kundenverwaltung, [$customerController, 'sendMessage']));

$router->get('/cases', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'index']));
$router->get('/cases/neu', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'create']));
$router->post('/cases', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'store']));
$router->get('/cases/{id}', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'show']));
$router->get('/cases/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'edit']));
$router->post('/cases/{id}', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'update']));
$router->post('/cases/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'destroy']));

$router->post('/cases/{id}/inventurnummern', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'addInventoryNumber']));
$router->post('/case-inventurnummern/{id}', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'updateInventoryNumber']));
$router->post('/case-inventurnummern/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'deleteInventoryNumber']));

$router->post('/case-inventurnummern/{id}/inhalt', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'addContentItem']));
$router->post('/case-inhalt/{id}', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'updateContentItem']));
$router->post('/case-inhalt/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$caseController, 'deleteContentItem']));

$router->get('/lieferanten', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'index']));
// Live-Suche (JSON) für die Lieferantenauswahl in Bestell-/
// Wareneingangsformularen — `null`-Permission, siehe Begründung bei
// '/hersteller/suche' oben.
$router->get('/lieferanten/suche', $auth->guard(null, [$supplierController, 'search']));
$router->get('/lieferanten/neu', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'create']));
$router->post('/lieferanten', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'store']));
$router->get('/lieferanten/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'edit']));
$router->post('/lieferanten/{id}', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'update']));
$router->post('/lieferanten/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$supplierController, 'destroy']));

$router->get('/bestellungen', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'index']));
$router->get('/bestellungen/neu', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'create']));
$router->post('/bestellungen', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'store']));
$router->get('/bestellungen/{id}', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'show']));
$router->get('/bestellungen/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'edit']));
$router->post('/bestellungen/{id}', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'update']));
$router->post('/bestellungen/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'destroy']));
$router->post('/bestellungen/{id}/bestellt', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'markOrdered']));
$router->post('/bestellungen/{id}/storniert', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'markCancelled']));
$router->post('/bestellungen/{id}/wareneingang', $auth->guard(Permission::Artikelverwaltung, [$purchaseOrderController, 'receiveItem']));
$router->get('/verkaeufe', $auth->guard(Permission::Artikelverwaltung, [$salesController, 'index']));

$router->get('/fahrzeuge', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'index']));
$router->get('/fahrzeuge/neu', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'create']));
$router->post('/fahrzeuge', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'store']));
$router->get('/fahrzeuge/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'edit']));
$router->post('/fahrzeuge/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'update']));
$router->post('/fahrzeuge/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$vehicleController, 'destroy']));

// Gutscheine: Verwaltung (Anlegen/Bearbeiten/Löschen) ist Administration
// vorbehalten (finanziell sensibel), die Einlösung einem Gutscheins bei
// einer konkreten Vermietung läuft dagegen über die normalen
// Vermietungs-Routen unter Permission::AusgabeRuecknahme, siehe unten.
$router->get('/gutscheine', $auth->guard(Permission::Administration, [$voucherController, 'index']));
$router->get('/gutscheine/neu', $auth->guard(Permission::Administration, [$voucherController, 'create']));
$router->post('/gutscheine', $auth->guard(Permission::Administration, [$voucherController, 'store']));
$router->get('/gutscheine/{id}/bearbeiten', $auth->guard(Permission::Administration, [$voucherController, 'edit']));
$router->post('/gutscheine/{id}', $auth->guard(Permission::Administration, [$voucherController, 'update']));
$router->post('/gutscheine/{id}/loeschen', $auth->guard(Permission::Administration, [$voucherController, 'destroy']));

$router->get('/veranstaltungsorte', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'index']));
// Live-Suche (JSON) für das Veranstaltungsort-Freitextfeld in Angebots-/
// Rechnungs-/Auftragsbestätigungs-/Vermietungsformularen — `null`-
// Permission, siehe Begründung bei '/hersteller/suche' oben.
$router->get('/veranstaltungsorte/suche', $auth->guard(null, [$venueController, 'search']));
$router->get('/veranstaltungsorte/neu', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'create']));
$router->post('/veranstaltungsorte', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'store']));
$router->get('/veranstaltungsorte/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'edit']));
$router->post('/veranstaltungsorte/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'update']));
$router->post('/veranstaltungsorte/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'destroy']));
$router->post('/veranstaltungsorte/{id}/stromanschluesse', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'addPowerConnection']));
$router->post('/veranstaltungsorte/{id}/stromanschluesse/{connectionId}', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'updatePowerConnection']));
$router->post('/veranstaltungsorte/{id}/stromanschluesse/{connectionId}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$venueController, 'deletePowerConnection']));

$router->get('/lagerorte', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'index']));
$router->get('/lagerorte/neu', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'create']));
$router->post('/lagerorte', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'store']));
$router->get('/lagerorte/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'edit']));
$router->post('/lagerorte/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'update']));
$router->post('/lagerorte/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$warehouseController, 'destroy']));

// Siehe /kunden/suche bzw. /artikel/suche für die identische Begründung
// (Permission::null statt AusgabeRuecknahme) — die Live-Suche für die
// Veranstaltungs-Verknüpfung auf Rechnungen (invoices/form.php) darf von
// jedem angemeldeten Benutzer genutzt werden, unabhängig von dessen
// Rechten in der Vermietungsverwaltung selbst. Vor den {id}-Routen
// platziert, damit "suche" nicht als {id} interpretiert wird.
$router->get('/veranstaltungen/suche', $auth->guard(null, [$rentalController, 'search']));
$router->get('/veranstaltungen/{id}/logistik', $auth->guard(null, [$rentalController, 'logistics']));
$router->get('/veranstaltungen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'index']));
$router->get('/veranstaltungen/neu', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'create']));
$router->post('/veranstaltungen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'store']));
$router->get('/veranstaltungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'show']));
$router->get('/veranstaltungen/{id}/packliste.pdf', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'exportPackingListPdf']));
$router->post('/veranstaltungen/{id}/packliste/email', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'emailPackingListPdf']));
$router->post('/veranstaltungen/{id}/rechnung-erstellen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'createInvoice']));
$router->get('/veranstaltungen/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'edit']));
$router->post('/veranstaltungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'update']));
$router->post('/veranstaltungen/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'destroy']));
$router->post('/veranstaltungen/{id}/logistik', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'updateLogistics']));

$router->post('/veranstaltungen/{id}/artikel', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'addItem']));
$router->post('/veranstaltungsposten/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'updateItem']));
$router->post('/veranstaltungsposten/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'deleteItem']));

$router->get('/veranstaltungen/{id}/ausgabe', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'checklist']));
$router->post('/veranstaltungen/{id}/ausgabe', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'issueItem']));
$router->post('/ausgaben/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'unissueItem']));
$router->post('/veranstaltungen/{id}/zubehoer-pruefung', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'confirmAccessories']));
$router->post('/veranstaltungen/{id}/vor-ausgabe-pruefung', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'confirmPreIssueChecks']));
$router->post('/veranstaltungen/{id}/vermietbedingungen-unterschrift', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'signTerms']));
$router->post('/veranstaltungen/{id}/ausweispruefung', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'confirmIdChecked']));

$router->get('/veranstaltungen/{id}/rueckgabe', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'returnChecklist']));
$router->post('/veranstaltungen/{id}/rueckgabe', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'returnItem']));

// Beladungsliste: Zuordnung ausgegebener Posten zu Fahrzeugen inkl.
// Anhänger-Zugfahrzeug-Zuordnung und Überladungs-Berechnung (siehe
// GetRentalLoadingListHandler/VehicleLoadCalculator).
$router->get('/veranstaltungen/{id}/beladung', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'loadingList']));
$router->post('/veranstaltungen/{id}/beladung/zuordnen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'assignVehicleLoad']));
$router->post('/veranstaltungen/{id}/beladung/entfernen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'removeVehicleLoad']));
$router->post('/veranstaltungen/{id}/beladung/zugfahrzeug', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'setVehicleTow']));
$router->post('/veranstaltungen/{id}/beladung/zugfahrzeug/entfernen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'removeVehicleTow']));

$router->post('/veranstaltungen/{id}/gutschein', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'redeemVoucher']));
$router->post('/veranstaltungen/{id}/gutschein/entfernen', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'removeVoucherRedemption']));
$router->post('/veranstaltungen/{id}/hinweisdokumente/senden', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'sendNoticeDocuments']));

$router->post('/veranstaltungen/{id}/kundencheckliste/link', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'issueCustomerChecklistLink']));
$router->post('/veranstaltungen/{id}/kundencheckliste/link/erneuern', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'regenerateCustomerChecklistLink']));
$router->post('/veranstaltungen/{id}/kundencheckliste/senden', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'emailCustomerChecklistLink']));

// Minutenprogramme (Ablaufpläne) einer Veranstaltung, siehe App\Domain\MinuteProgram.
$router->get('/veranstaltungen/{rentalId}/minutenprogramme/neu', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'create']));
$router->post('/veranstaltungen/{rentalId}/minutenprogramme', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'store']));
$router->get('/minutenprogramme/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'show']));
$router->get('/minutenprogramme/{id}/bearbeiten', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'edit']));
$router->post('/minutenprogramme/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'update']));
$router->post('/minutenprogramme/{id}/loeschen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'destroy']));
$router->post('/minutenprogramme/{id}/verzoegerung', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'setDelay']));
$router->get('/minutenprogramme/{id}/vollbild', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'fullscreen']));
$router->get('/minutenprogramme/{id}/countdown.json', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'countdown']));
$router->post('/minutenprogramme/{id}/punkte', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'addItem']));
$router->post('/minutenprogramm-punkte/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'updateItem']));
$router->post('/minutenprogramm-punkte/{id}/loeschen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'deleteItem']));
$router->post('/minutenprogramm-punkte/{id}/verschieben', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'moveItem']));
$router->post('/minutenprogramme/{id}/als-vorlage-speichern', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'saveAsTemplate']));
$router->post('/minutenprogramme/{id}/freigabe-links', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'createShareLink']));
$router->post('/freigabe-links/{id}/widerrufen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramController, 'revokeShareLink']));

// Minutenprogramm-Vorlagen (Stammdaten, unabhängig von einer konkreten Veranstaltung).
$router->get('/minutenprogramm-vorlagen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'index']));
$router->get('/minutenprogramm-vorlagen/neu', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'create']));
$router->post('/minutenprogramm-vorlagen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'store']));
$router->get('/minutenprogramm-vorlagen/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'show']));
$router->get('/minutenprogramm-vorlagen/{id}/bearbeiten', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'edit']));
$router->post('/minutenprogramm-vorlagen/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'update']));
$router->post('/minutenprogramm-vorlagen/{id}/loeschen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'destroy']));
$router->post('/minutenprogramm-vorlagen/{id}/punkte', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'addItem']));
$router->post('/minutenprogramm-vorlagen-punkte/{id}', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'updateItem']));
$router->post('/minutenprogramm-vorlagen-punkte/{id}/loeschen', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'deleteItem']));
$router->post('/minutenprogramm-vorlagen-punkte/{id}/verschieben', $auth->guard(Permission::Minutenprogramme, [$minuteProgramTemplateController, 'moveItem']));

$router->get('/angebote', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'index']));
$router->get('/angebote/neu', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'create']));
$router->post('/angebote', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'store']));
$router->get('/angebote/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'show']));
$router->get('/angebote/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'edit']));
$router->post('/angebote/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'update']));
$router->post('/angebote/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'destroy']));
$router->post('/angebote/{id}/versendet', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'markSent']));
$router->post('/angebote/{id}/angenommen', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'markAccepted']));
$router->post('/angebote/{id}/abgelehnt', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'markDeclined']));
$router->post('/angebote/{id}/umwandeln', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'convertToRental']));
$router->get('/angebote/{id}/angebot.pdf', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'exportPdf']));
$router->post('/angebote/{id}/email', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'emailPdf']));
$router->post('/angebote/{id}/rechnung-erstellen', $auth->guard(Permission::AusgabeRuecknahme, [$offerController, 'createInvoice']));
$router->post('/angebote/{id}/auftragsbestaetigung-erstellen', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'createFromOffer']));

$router->get('/auftragsbestaetigungen', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'index']));
$router->get('/auftragsbestaetigungen/neu', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'create']));
$router->post('/auftragsbestaetigungen', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'store']));
$router->get('/auftragsbestaetigungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'show']));
$router->get('/auftragsbestaetigungen/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'edit']));
$router->post('/auftragsbestaetigungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'update']));
$router->post('/auftragsbestaetigungen/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'destroy']));
$router->post('/auftragsbestaetigungen/{id}/versendet', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'markSent']));
$router->post('/auftragsbestaetigungen/{id}/bestaetigt', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'markConfirmed']));
$router->post('/auftragsbestaetigungen/{id}/storniert', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'markCancelled']));
$router->get('/auftragsbestaetigungen/{id}/auftragsbestaetigung.pdf', $auth->guard(Permission::AusgabeRuecknahme, [$orderConfirmationController, 'exportPdf']));

$router->get('/rechnungen', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'index']));
$router->get('/rechnungen/neu', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'create']));
$router->post('/rechnungen', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'store']));
$router->get('/rechnungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'show']));
$router->get('/rechnungen/{id}/bearbeiten', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'edit']));
$router->post('/rechnungen/{id}', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'update']));
$router->post('/rechnungen/{id}/loeschen', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'destroy']));
$router->post('/rechnungen/{id}/versenden', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'markSent']));
$router->post('/rechnungen/{id}/bezahlt', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'markPaid']));
$router->post('/rechnungen/{id}/stornieren', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'markCancelled']));
$router->get('/rechnungen/{id}/pdf', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'exportPdf']));
$router->get('/rechnungen/{id}/xrechnung', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'exportXRechnung']));
$router->post('/rechnungen/{id}/email', $auth->guard(Permission::AusgabeRuecknahme, [$invoiceController, 'emailPdf']));
$router->get('/zahlungsabgleich', $auth->guard(Permission::AusgabeRuecknahme, [$bankImportController, 'index']));
$router->post('/zahlungsabgleich/importieren', $auth->guard(Permission::AusgabeRuecknahme, [$bankImportController, 'import']));
$router->post('/zahlungsabgleich/bestaetigen', $auth->guard(Permission::AusgabeRuecknahme, [$bankImportController, 'confirm']));
$router->post('/zahlungsabgleich/abbrechen', $auth->guard(Permission::AusgabeRuecknahme, [$bankImportController, 'cancel']));

$router->post('/rechnungen/{id}/mahnungen', $auth->guard(Permission::AusgabeRuecknahme, [$dunningController, 'store']));
$router->get('/mahnungen/{dunningId}/pdf', $auth->guard(Permission::AusgabeRuecknahme, [$dunningController, 'exportPdf']));
$router->post('/mahnungen/{dunningId}/email', $auth->guard(Permission::AusgabeRuecknahme, [$dunningController, 'emailPdf']));

$router->get('/belege', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'index']));
$router->get('/belege/posteingang', $auth->guard(Permission::Artikelverwaltung, [$receiptInboxController, 'index']));
$router->get('/belege/posteingang/anhaenge/{id}/download', $auth->guard(Permission::Artikelverwaltung, [$receiptInboxController, 'downloadAttachment']));
$router->get('/belege/posteingang/anhaenge/{id}/beleg-erstellen', $auth->guard(Permission::Artikelverwaltung, [$receiptInboxController, 'createReceipt']));
$router->post('/belege/posteingang/anhaenge/{id}/beleg-erstellen', $auth->guard(Permission::Artikelverwaltung, [$receiptInboxController, 'storeReceipt']));
$router->get('/belege/neu', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'create']));
$router->post('/belege', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'store']));
$router->get('/belege/{id}', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'show']));
$router->get('/belege/{id}/bearbeiten', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'edit']));
$router->post('/belege/{id}', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'update']));
$router->post('/belege/{id}/loeschen', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'destroy']));
$router->post('/belege/{id}/bezahlt', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'markPaid']));
$router->post('/belege/{id}/offen', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'markOpen']));
$router->get('/belege/{id}/download', $auth->guard(Permission::Artikelverwaltung, [$receiptController, 'download']));
$router->post('/ausgaben/{id}/rueckgabe-rueckgaengig', $auth->guard(Permission::AusgabeRuecknahme, [$rentalController, 'undoReturnItem']));

$router->get('/kalender', $auth->guard(Permission::AusgabeRuecknahme, [$calendarController, 'index']));

// Berichte (nur Administration) — aktuell nur die Einnahmen-/Ausgabenübersicht.
$router->get('/berichte/einnahmen-ausgaben', $auth->guard(Permission::Administration, [$reportingController, 'incomeExpense']));
$router->get('/berichte/umsatzprognose', $auth->guard(Permission::Administration, [$reportingController, 'revenueForecast']));

// Benutzerverwaltung (nur Administration).
$router->get('/benutzer', $auth->guard(Permission::Administration, [$userController, 'index']));
$router->get('/benutzer/neu', $auth->guard(Permission::Administration, [$userController, 'create']));
$router->post('/benutzer', $auth->guard(Permission::Administration, [$userController, 'store']));
$router->get('/benutzer/{id}/bearbeiten', $auth->guard(Permission::Administration, [$userController, 'edit']));
$router->post('/benutzer/{id}', $auth->guard(Permission::Administration, [$userController, 'update']));
$router->post('/benutzer/{id}/loeschen', $auth->guard(Permission::Administration, [$userController, 'destroy']));
$router->get('/benutzer/{id}/passwort', $auth->guard(Permission::Administration, [$userController, 'showResetPassword']));
$router->post('/benutzer/{id}/passwort', $auth->guard(Permission::Administration, [$userController, 'resetPassword']));
$router->post('/benutzer/{id}/2fa-zuruecksetzen', $auth->guard(Permission::Administration, [$userController, 'resetTwoFactor']));
$router->get('/benutzer/{id}/sitzungen', $auth->guard(Permission::Administration, [$userController, 'showSessions']));
$router->post('/benutzer/{id}/sitzungen/{sessionId}/beenden', $auth->guard(Permission::Administration, [$userController, 'revokeSession']));
$router->post('/benutzer/{id}/sitzungen/alle-beenden', $auth->guard(Permission::Administration, [$userController, 'revokeAllSessions']));
$router->get('/benutzer/impersonationen', $auth->guard(Permission::Administration, [$userController, 'listImpersonations']));
$router->post('/benutzer/{id}/impersonieren', $auth->guard(Permission::Administration, [$userController, 'startImpersonation']));
// Bewusst OHNE Permission::Administration: während einer laufenden
// Impersonation hat der aktuell wirksame Benutzer (der Zielbenutzer)
// möglicherweise gar kein Administration-Recht mehr. AuthGuard lässt diesen
// Pfad trotzdem durch, solange Session::isImpersonating() aktiv ist (siehe
// AuthGuard::IMPERSONATION_STOP_PATH).
$router->post('/benutzer/impersonation/beenden', $auth->guard(null, [$userController, 'stopImpersonation']));

// SMTP-Einstellungen (nur Administration).
$router->get('/einstellungen/smtp', $auth->guard(Permission::Administration, [$smtpSettingsController, 'edit']));
$router->post('/einstellungen/smtp', $auth->guard(Permission::Administration, [$smtpSettingsController, 'update']));
$router->post('/einstellungen/smtp/test', $auth->guard(Permission::Administration, [$smtpSettingsController, 'sendTest']));

// Mailserver-Einstellungen für den Beleg-Posteingang (nur Administration).
$router->get('/einstellungen/mailserver', $auth->guard(Permission::Administration, [$mailboxSettingsController, 'edit']));
$router->post('/einstellungen/mailserver', $auth->guard(Permission::Administration, [$mailboxSettingsController, 'update']));
$router->post('/einstellungen/mailserver/testen', $auth->guard(Permission::Administration, [$mailboxSettingsController, 'sendTest']));
$router->post('/einstellungen/mailserver/erlaubte-absender', $auth->guard(Permission::Administration, [$mailboxSettingsController, 'storeAllowedSender']));
$router->post('/einstellungen/mailserver/erlaubte-absender/{id}/loeschen', $auth->guard(Permission::Administration, [$mailboxSettingsController, 'destroyAllowedSender']));

// Push-Benachrichtigungs-Einstellungen (VAPID-Schlüssel/Absenderadresse, nur Administration).
$router->get('/einstellungen/push', $auth->guard(Permission::Administration, [$pushSettingsController, 'edit']));
$router->post('/einstellungen/push', $auth->guard(Permission::Administration, [$pushSettingsController, 'update']));

// Messenger-Einstellungen (WhatsApp Business/Telegram/Signal, nur Administration).
$router->get('/einstellungen/messenger', $auth->guard(Permission::Administration, [$messagingSettingsController, 'edit']));
$router->post('/einstellungen/messenger', $auth->guard(Permission::Administration, [$messagingSettingsController, 'update']));
$router->get('/einstellungen/ki-werbebilder', $auth->guard(Permission::Administration, [$aiAdImageSettingsController, 'edit']));
$router->post('/einstellungen/ki-werbebilder', $auth->guard(Permission::Administration, [$aiAdImageSettingsController, 'update']));
$router->post('/einstellungen/messenger/test/{channel}', $auth->guard(Permission::Administration, [$messagingSettingsController, 'testConnection']));
$router->get('/einstellungen/messenger-vorlagen', $auth->guard(Permission::Administration, [$messageTemplateSettingsController, 'index']));
$router->get('/einstellungen/messenger-vorlagen/{channel}/{key}', $auth->guard(Permission::Administration, [$messageTemplateSettingsController, 'edit']));
$router->post('/einstellungen/messenger-vorlagen/{channel}/{key}', $auth->guard(Permission::Administration, [$messageTemplateSettingsController, 'update']));
$router->post('/einstellungen/messenger-vorlagen/{channel}/{key}/zuruecksetzen', $auth->guard(Permission::Administration, [$messageTemplateSettingsController, 'reset']));

// E-Mail-Vorlagen (nur Administration).
$router->get('/einstellungen/email-vorlagen', $auth->guard(Permission::Administration, [$emailTemplateSettingsController, 'index']));
$router->get('/einstellungen/email-vorlagen/{key}', $auth->guard(Permission::Administration, [$emailTemplateSettingsController, 'edit']));
$router->post('/einstellungen/email-vorlagen/{key}', $auth->guard(Permission::Administration, [$emailTemplateSettingsController, 'update']));
$router->post('/einstellungen/email-vorlagen/{key}/zuruecksetzen', $auth->guard(Permission::Administration, [$emailTemplateSettingsController, 'reset']));

$router->get('/einstellungen/preisstaffelung', $auth->guard(Permission::Administration, [$priceTierSettingsController, 'edit']));
$router->post('/einstellungen/preisstaffelung', $auth->guard(Permission::Administration, [$priceTierSettingsController, 'update']));

$router->get('/einstellungen/firma', $auth->guard(Permission::Administration, [$companySettingsController, 'edit']));
$router->post('/einstellungen/firma', $auth->guard(Permission::Administration, [$companySettingsController, 'update']));
$router->post('/einstellungen/firma/logo', $auth->guard(Permission::Administration, [$companySettingsController, 'uploadLogo']));
$router->post('/einstellungen/firma/logo/loeschen', $auth->guard(Permission::Administration, [$companySettingsController, 'deleteLogo']));
$router->get('/einstellungen/firma/logo', $auth->guard(Permission::Administration, [$companySettingsController, 'showLogo']));
$router->post('/einstellungen/firma/logo-dunkel', $auth->guard(Permission::Administration, [$companySettingsController, 'uploadDarkLogo']));
$router->post('/einstellungen/firma/logo-dunkel/loeschen', $auth->guard(Permission::Administration, [$companySettingsController, 'deleteDarkLogo']));
$router->get('/einstellungen/firma/logo-dunkel', $auth->guard(Permission::Administration, [$companySettingsController, 'showDarkLogo']));
$router->post('/einstellungen/firma/hintergrundbild', $auth->guard(Permission::Administration, [$companySettingsController, 'uploadBackgroundImage']));
$router->post('/einstellungen/firma/hintergrundbild/loeschen', $auth->guard(Permission::Administration, [$companySettingsController, 'deleteBackgroundImage']));
$router->get('/einstellungen/firma/hintergrundbild', $auth->guard(Permission::Administration, [$companySettingsController, 'showBackgroundImage']));

$router->get('/einstellungen/integrationen', $auth->guard(Permission::Administration, [$integrationSettingsController, 'edit']));
$router->post('/einstellungen/integrationen/basis-url', $auth->guard(Permission::Administration, [$integrationSettingsController, 'updateBase']));
$router->post('/einstellungen/integrationen/google-merchant', $auth->guard(Permission::Administration, [$integrationSettingsController, 'updateGoogleMerchant']));
$router->post('/einstellungen/integrationen/google-merchant/zugangsdaten', $auth->guard(Permission::Administration, [$integrationSettingsController, 'uploadGoogleMerchantServiceAccount']));
$router->post('/einstellungen/integrationen/google-merchant/zugangsdaten/loeschen', $auth->guard(Permission::Administration, [$integrationSettingsController, 'deleteGoogleMerchantServiceAccount']));
$router->post('/einstellungen/integrationen/google-business', $auth->guard(Permission::Administration, [$integrationSettingsController, 'updateGoogleBusinessCredentials']));
$router->get('/einstellungen/integrationen/google-business/verbinden', $auth->guard(Permission::Administration, [$integrationSettingsController, 'connectGoogleBusiness']));
$router->get('/einstellungen/integrationen/google-business/callback', $auth->guard(Permission::Administration, [$integrationSettingsController, 'googleBusinessCallback']));
$router->post('/einstellungen/integrationen/google-business/trennen', $auth->guard(Permission::Administration, [$integrationSettingsController, 'disconnectGoogleBusiness']));
$router->post('/einstellungen/integrationen/whatsapp', $auth->guard(Permission::Administration, [$integrationSettingsController, 'updateWhatsapp']));

// Öffentlich (kein Login), siehe PublicProductController-Docblock.
$router->get('/produkte/{id}/bild', [$publicProductController, 'image']);
$router->get('/produkte/{id}', [$publicProductController, 'show']);

// Öffentlich (kein Login), siehe CustomerChecklistController-Docblock.
$router->get('/kundencheckliste/{token}', [$customerChecklistController, 'show']);
$router->post('/kundencheckliste/{token}/{item}', [$customerChecklistController, 'toggle']);

// Minutenprogramm-Freigabe-Links: öffentlich, unangemeldet erreichbar (siehe MinuteProgramShareController).
$router->get('/minutenprogramm/freigabe/{token}', [$minuteProgramShareController, 'show']);
$router->get('/minutenprogramm/freigabe/{token}/vollbild', [$minuteProgramShareController, 'fullscreen']);
$router->get('/minutenprogramm/freigabe/{token}/countdown.json', [$minuteProgramShareController, 'countdown']);
$router->post('/minutenprogramm/freigabe/{token}/verzoegerung', [$minuteProgramShareController, 'setDelay']);

$router->get('/einstellungen/rechtliches', $auth->guard(Permission::Administration, [$legalSettingsController, 'edit']));
$router->post('/einstellungen/rechtliches', $auth->guard(Permission::Administration, [$legalSettingsController, 'update']));

// Angebotsvorlage (PDF-Einleitungs-/Schlusstext, nur Administration).
$router->get('/einstellungen/angebotsvorlage', $auth->guard(Permission::Administration, [$offerPdfTemplateSettingsController, 'edit']));
$router->post('/einstellungen/angebotsvorlage', $auth->guard(Permission::Administration, [$offerPdfTemplateSettingsController, 'update']));

$router->get('/einstellungen/rechnung', $auth->guard(Permission::Administration, [$invoiceSettingsController, 'edit']));
$router->post('/einstellungen/rechnung', $auth->guard(Permission::Administration, [$invoiceSettingsController, 'update']));
$router->get('/einstellungen/nummerierung', $auth->guard(Permission::Administration, [$numberingSettingsController, 'edit']));
$router->post('/einstellungen/nummerierung', $auth->guard(Permission::Administration, [$numberingSettingsController, 'update']));

$router->get('/einstellungen/mahnwesen', $auth->guard(Permission::Administration, [$dunningLevelController, 'index']));
$router->get('/einstellungen/mahnwesen/{id}/bearbeiten', $auth->guard(Permission::Administration, [$dunningLevelController, 'edit']));
$router->post('/einstellungen/mahnwesen', $auth->guard(Permission::Administration, [$dunningLevelController, 'store']));
// WICHTIG — Reihenfolge: die literale Route "einstellungen" MUSS vor der
// Platzhalter-Route "{id}" registriert werden, da Router::dispatch() Routen
// in Registrierungsreihenfolge prüft und die erste passende gewinnt — sonst
// würde "einstellungen" fälschlich als {id} interpretiert.
$router->post('/einstellungen/mahnwesen/einstellungen', $auth->guard(Permission::Administration, [$dunningLevelController, 'updateSettings']));
$router->post('/einstellungen/mahnwesen/{id}', $auth->guard(Permission::Administration, [$dunningLevelController, 'update']));
$router->post('/einstellungen/mahnwesen/{id}/loeschen', $auth->guard(Permission::Administration, [$dunningLevelController, 'destroy']));

$router->get('/einstellungen/preise', $auth->guard(Permission::Administration, [$pricingSettingsController, 'edit']));
$router->post('/einstellungen/preise', $auth->guard(Permission::Administration, [$pricingSettingsController, 'update']));

$router->get('/einstellungen/gutscheine', $auth->guard(Permission::Administration, [$voucherSettingsController, 'edit']));
$router->post('/einstellungen/gutscheine', $auth->guard(Permission::Administration, [$voucherSettingsController, 'update']));

$router->get('/einstellungen/rechnungsvorlage', $auth->guard(Permission::Administration, [$invoicePdfTemplateSettingsController, 'edit']));
$router->post('/einstellungen/rechnungsvorlage', $auth->guard(Permission::Administration, [$invoicePdfTemplateSettingsController, 'update']));

$router->get('/einstellungen/pdf-design', $auth->guard(Permission::Administration, [$pdfDesignSettingsController, 'edit']));
$router->post('/einstellungen/pdf-design', $auth->guard(Permission::Administration, [$pdfDesignSettingsController, 'update']));

$router->get('/einstellungen/pdf-layout', $auth->guard(Permission::Administration, [$pdfLayoutSettingsController, 'edit']));
$router->post('/einstellungen/pdf-layout', $auth->guard(Permission::Administration, [$pdfLayoutSettingsController, 'update']));

$router->get('/einstellungen/erinnerungen', $auth->guard(Permission::Administration, [$rentalReminderSettingsController, 'edit']));
$router->post('/einstellungen/erinnerungen', $auth->guard(Permission::Administration, [$rentalReminderSettingsController, 'update']));
$router->get('/einstellungen/rechnungserinnerungen', $auth->guard(Permission::Administration, [$invoiceDueReminderSettingsController, 'edit']));
$router->post('/einstellungen/rechnungserinnerungen', $auth->guard(Permission::Administration, [$invoiceDueReminderSettingsController, 'update']));

$router->get('/einstellungen/ueberziehung', $auth->guard(Permission::Administration, [$overageSettingsController, 'edit']));
$router->post('/einstellungen/ueberziehung', $auth->guard(Permission::Administration, [$overageSettingsController, 'update']));

// Etikettendrucker (nur Administration).
$router->get('/einstellungen/etikettendrucker', $auth->guard(Permission::Administration, [$labelPrinterController, 'index']));
$router->get('/einstellungen/etikettendrucker/neu', $auth->guard(Permission::Administration, [$labelPrinterController, 'create']));
$router->post('/einstellungen/etikettendrucker', $auth->guard(Permission::Administration, [$labelPrinterController, 'store']));
$router->get('/einstellungen/etikettendrucker/{id}/bearbeiten', $auth->guard(Permission::Administration, [$labelPrinterController, 'edit']));
$router->post('/einstellungen/etikettendrucker/{id}', $auth->guard(Permission::Administration, [$labelPrinterController, 'update']));
$router->post('/einstellungen/etikettendrucker/{id}/loeschen', $auth->guard(Permission::Administration, [$labelPrinterController, 'destroy']));
$router->post('/einstellungen/etikettendrucker/{id}/testdruck', $auth->guard(Permission::Administration, [$labelPrinterController, 'testPrint']));

// Ziellaufwerke (externe Speicherziele für den automatischen Dokument-Push, nur Administration).
$router->get('/einstellungen/speicherziele', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'index']));
$router->post('/einstellungen/speicherziele', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'store']));
$router->get('/einstellungen/speicherziele/zuordnungen', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'assignments']));
$router->post('/einstellungen/speicherziele/zuordnungen', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'storeAssignment']));
$router->post('/einstellungen/speicherziele/zuordnungen/{id}/loeschen', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'destroyAssignment']));
$router->get('/einstellungen/speicherziele/{id}/bearbeiten', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'edit']));
$router->post('/einstellungen/speicherziele/{id}', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'update']));
$router->post('/einstellungen/speicherziele/{id}/loeschen', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'destroy']));
$router->post('/einstellungen/speicherziele/{id}/testen', $auth->guard(Permission::Administration, [$documentStorageSettingsController, 'testConnection']));

// Backup-Export/-Import (nur Administration) — siehe BackupSettingsController.
$router->get('/einstellungen/sicherung', $auth->guard(Permission::Administration, [$backupSettingsController, 'index']));
$router->post('/einstellungen/sicherung/export', $auth->guard(Permission::Administration, [$backupSettingsController, 'export']));
$router->post('/einstellungen/sicherung/import', $auth->guard(Permission::Administration, [$backupSettingsController, 'import']));

// REST-API-Einstellungen (Token-Verwaltung, nur Administration).
$router->get('/einstellungen/api', $auth->guard(Permission::Administration, [$apiSettingsController, 'edit']));
$router->post('/einstellungen/api', $auth->guard(Permission::Administration, [$apiSettingsController, 'updateSettings']));
$router->post('/einstellungen/api/tokens', $auth->guard(Permission::Administration, [$apiSettingsController, 'createToken']));
$router->post('/einstellungen/api/tokens/{id}', $auth->guard(Permission::Administration, [$apiSettingsController, 'updateToken']));
$router->post('/einstellungen/api/tokens/{id}/aktivieren', $auth->guard(Permission::Administration, [$apiSettingsController, 'enableToken']));
$router->post('/einstellungen/api/tokens/{id}/deaktivieren', $auth->guard(Permission::Administration, [$apiSettingsController, 'disableToken']));
$router->post('/einstellungen/api/tokens/{id}/loeschen', $auth->guard(Permission::Administration, [$apiSettingsController, 'deleteToken']));

// Kalender-Einstellungen (CalDAV-Server, nur Administration).
$router->get('/einstellungen/kalender', $auth->guard(Permission::Administration, [$calendarSettingsController, 'edit']));
$router->post('/einstellungen/kalender', $auth->guard(Permission::Administration, [$calendarSettingsController, 'createCalendar']));
// Statische Pfade VOR den {id}-Routen registriert (Router.php matcht in
// Registrierungsreihenfolge, "signatur" würde sonst als {id} interpretiert).
$router->post('/einstellungen/kalender/signatur', $auth->guard(Permission::Administration, [$calendarSettingsController, 'uploadSigningCertificate']));
$router->post('/einstellungen/kalender/signatur/loeschen', $auth->guard(Permission::Administration, [$calendarSettingsController, 'deleteSigningCertificate']));
$router->post('/einstellungen/kalender/{id}', $auth->guard(Permission::Administration, [$calendarSettingsController, 'updateCalendar']));
$router->post('/einstellungen/kalender/{id}/aktivieren', $auth->guard(Permission::Administration, [$calendarSettingsController, 'enableCalendar']));
$router->post('/einstellungen/kalender/{id}/deaktivieren', $auth->guard(Permission::Administration, [$calendarSettingsController, 'disableCalendar']));
$router->post('/einstellungen/kalender/{id}/loeschen', $auth->guard(Permission::Administration, [$calendarSettingsController, 'deleteCalendar']));
$router->post('/einstellungen/kalender/{id}/zugriff', $auth->guard(Permission::Administration, [$calendarSettingsController, 'updateAccess']));

// Öffentliche REST-API (Token-Auth über ApiTokenGuard, bewusst OHNE Session/
// CSRF — siehe ApiTokenGuard-Docblock). GET-only, daher ohnehin nicht
// CSRF-pflichtig (siehe Router::dispatch()).
$router->get('/api/v1/articles', $apiTokenGuard->guard([$articleApiController, 'index']));
$router->get('/api/v1/articles/{id}', $apiTokenGuard->guard([$articleApiController, 'show']));
$router->get('/api/v1/articles/{id}/images/{imageId}', $apiTokenGuard->guard([$articleApiController, 'image']));

// CalDAV-Server (RFC 4791) — rein lesend, Basic Auth via CalDavAuthGuard
// (siehe CalDavController für die vollständige Beschreibung des Ablaufs).
// Bewusst OHNE $auth->guard()/$apiTokenGuard->guard()-Wrapper: der
// Controller prüft die Basic-Auth-Zugangsdaten selbst (authenticate()),
// da CalDAV-eigene Semantik (WWW-Authenticate-Header, 401 vor jeder
// anderen Prüfung) nötig ist.
$router->get('/.well-known/caldav', [$calDavController, 'wellKnown']);
$router->propfind('/.well-known/caldav', [$calDavController, 'wellKnown']);
$router->options('/.well-known/caldav', [$calDavController, 'options']);
$router->options('/caldav/', [$calDavController, 'options']);
$router->propfind('/caldav/', [$calDavController, 'root']);
$router->options('/caldav/principals/{userId}/', [$calDavController, 'options']);
$router->propfind('/caldav/principals/{userId}/', [$calDavController, 'principal']);
$router->options('/caldav/calendars/{userId}/', [$calDavController, 'options']);
$router->propfind('/caldav/calendars/{userId}/', [$calDavController, 'calendarHome']);
$router->options('/caldav/calendars/{userId}/{calendarId}/', [$calDavController, 'options']);
$router->propfind('/caldav/calendars/{userId}/{calendarId}/', [$calDavController, 'calendarCollection']);
$router->report('/caldav/calendars/{userId}/{calendarId}/', [$calDavController, 'report']);
$router->options('/caldav/calendars/{userId}/{calendarId}/{eventId}.ics', [$calDavController, 'options']);
$router->get('/caldav/calendars/{userId}/{calendarId}/{eventId}.ics', [$calDavController, 'getEvent']);

// CardDAV-Server (RFC 6352) — rein lesend, Basic Auth via CardDavAuthGuard
// (siehe CardDavController für die vollständige Beschreibung des Ablaufs).
// Bewusst OHNE $auth->guard()/$apiTokenGuard->guard()-Wrapper, aus
// demselben Grund wie beim CalDAV-Server oben.
$router->get('/.well-known/carddav', [$cardDavController, 'wellKnown']);
$router->propfind('/.well-known/carddav', [$cardDavController, 'wellKnown']);
$router->options('/.well-known/carddav', [$cardDavController, 'options']);
$router->options('/carddav/', [$cardDavController, 'options']);
$router->propfind('/carddav/', [$cardDavController, 'root']);
$router->options('/carddav/principals/{userId}/', [$cardDavController, 'options']);
$router->propfind('/carddav/principals/{userId}/', [$cardDavController, 'principal']);
$router->options('/carddav/addressbooks/{userId}/', [$cardDavController, 'options']);
$router->propfind('/carddav/addressbooks/{userId}/', [$cardDavController, 'addressBookHome']);
$router->options('/carddav/addressbooks/{userId}/{bookId}/', [$cardDavController, 'options']);
$router->propfind('/carddav/addressbooks/{userId}/{bookId}/', [$cardDavController, 'addressBookCollection']);
$router->report('/carddav/addressbooks/{userId}/{bookId}/', [$cardDavController, 'report']);
$router->options('/carddav/addressbooks/{userId}/{bookId}/{contactId}.vcf', [$cardDavController, 'options']);
$router->get('/carddav/addressbooks/{userId}/{bookId}/{contactId}.vcf', [$cardDavController, 'getContact']);

// Impressum/Datenschutzerklärung: öffentlich, ohne Anmeldung erreichbar
// (siehe auch Footer-Links in layout.php und AuthGuard::PUBLIC_PATHS, die
// hier bewusst NICHT geändert werden muss, da diese Routen ohne guard()
// registriert sind).
$router->get('/impressum', [$legalPageController, 'imprint']);
$router->get('/datenschutz', [$legalPageController, 'privacy']);

return $router;
