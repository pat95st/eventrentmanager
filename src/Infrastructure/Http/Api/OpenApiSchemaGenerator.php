<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Generates OpenAPI schemas for the entities exposed by the REST API from
 * their public properties — the same source Normalizer reads — so the
 * documented fields cannot drift from the actual responses. Output:
 * docs/openapi/components/generated.yaml (bin/generate-api-schemas.php;
 * OpenApiSchemaUpToDateTest fails when the file is stale).
 *
 * Request bodies are generated the same way from the constructor parameters
 * of the commands the API dispatches (see CommandFactory): "<Name>Input"
 * with the required fields of the create command, "<Name>Patch" from the
 * update command with every field optional.
 */
final class OpenApiSchemaGenerator
{
    /**
     * Schema name => [entity class, excluded camelCase properties, description].
     *
     * @return array<string, array{0: class-string, 1: list<string>, 2: string}>
     */
    public static function entities(): array
    {
        return [
            'Customer' => [\App\Domain\Customer\Customer::class, [], 'Customer'],
            'CustomerContact' => [\App\Domain\Customer\CustomerContact::class, ['customerId'], 'Contact of a customer'],
            'Article' => [\App\Domain\Article\Article::class, ['images', 'isBillable', 'isActive'], 'Article (raw fields)'],
            'InventoryNumber' => [\App\Domain\InventoryNumber\InventoryNumber::class, [], 'Inventory number (unit of an article)'],
            'ArticleSet' => [\App\Domain\ArticleSet\ArticleSet::class, [], 'Set (fixed-price package)'],
            'Category' => [\App\Domain\Category\Category::class, [], 'Category'],
            'Manufacturer' => [\App\Domain\Manufacturer\Manufacturer::class, [], 'Manufacturer'],
            'LaborTimeArticle' => [\App\Domain\LaborTime\LaborTimeArticle::class, [], 'Labor time article'],
            'Supplier' => [\App\Domain\Supplier\Supplier::class, [], 'Supplier'],
            'Venue' => [\App\Domain\Venue\Venue::class, [], 'Venue'],
            'Warehouse' => [\App\Domain\Warehouse\Warehouse::class, [], 'Warehouse'],
            'Vehicle' => [\App\Domain\Vehicle\Vehicle::class, ['logbookQrToken'], 'Vehicle'],
            'Rental' => [\App\Domain\Rental\Rental::class, ['termsSignatureFile'], 'Event (rental)'],
            'RentalItem' => [\App\Domain\Rental\RentalItem::class, [], 'Scheduled item of an event'],
            'RentalIssuedItem' => [\App\Domain\Rental\RentalIssuedItem::class, [], 'Issued unit'],
            'RentalTechnician' => [\App\Domain\Technician\RentalTechnician::class, [], 'Technician assignment'],
            'Offer' => [\App\Domain\Offer\Offer::class, [], 'Offer'],
            'OrderConfirmation' => [\App\Domain\OrderConfirmation\OrderConfirmation::class, [], 'Order confirmation'],
            'Invoice' => [\App\Domain\Invoice\Invoice::class, [], 'Invoice'],
            'InvoiceCorrection' => [\App\Domain\InvoiceCorrection\InvoiceCorrection::class, [], 'Invoice correction'],
            'DunningNotice' => [\App\Domain\Dunning\DunningNotice::class, [], 'Dunning notice'],
            'DocumentTotals' => [\App\Domain\Pricing\DocumentTotals::class, [], 'Totals of a document (net/tax/gross)'],
            'Receipt' => [\App\Domain\Receipt\Receipt::class, ['storedFilename'], 'Receipt (incoming invoice)'],
            'BankAccount' => [\App\Domain\Settings\BankAccount::class, [], 'Bank account'],
            'AccountTransaction' => [\App\Domain\Banking\AccountTransaction::class, [], 'Account transaction (amounts in cents)'],
            'PurchaseOrder' => [\App\Domain\Purchase\PurchaseOrder::class, [], 'Purchase order to a supplier'],
            'PurchaseOrderItem' => [\App\Domain\Purchase\PurchaseOrderItem::class, [], 'Purchase order item'],
            'CaseItem' => [\App\Domain\CaseItem\CaseItem::class, [], 'Case'],
            'Rack' => [\App\Domain\Rack\Rack::class, [], 'Rack'],
            'Rma' => [\App\Domain\Rma\Rma::class, [], 'RMA / repair'],
            'ElectricalInspection' => [\App\Domain\ElectricalInspection\ElectricalInspection::class, [], 'Electrical inspection (DGUV V3)'],
            'Sale' => [\App\Domain\Sale\Sale::class, [], 'Sale'],
            'MinuteProgram' => [\App\Domain\MinuteProgram\MinuteProgram::class, [], 'Minute program'],
            'MinuteProgramTemplate' => [\App\Domain\MinuteProgram\MinuteProgramTemplate::class, [], 'Template for minute programs'],
        ];
    }

    /**
     * Request body schema name => [command class, excluded parameters, field renames (param => body field), description, all optional?].
     *
     * @return array<string, array{0: class-string, 1: list<string>, 2: array<string, string>, 3: string, 4: bool}>
     */
    public static function inputs(): array
    {
        $ns = 'App\\Application\\';
        $special = ['powerDistributor', 'cable', 'digitalStagebox', 'mixingConsole'];

        return [
            'CustomerInput' => [$ns . 'Customer\\Command\\CreateCustomer', [], [], 'New customer', false],
            'CustomerPatch' => [$ns . 'Customer\\Command\\UpdateCustomer', ['id'], [], 'Update customer (only the fields sent)', true],
            'ArticleInput' => [$ns . 'Article\\Command\\CreateArticle', $special, [], 'New article. Special details (power distributor, cable, stagebox, mixing console) only via the web interface.', false],
            'ArticlePatch' => [$ns . 'Article\\Command\\UpdateArticle', array_merge(['id'], $special), [], 'Update article (only the fields sent)', true],
            'InventoryNumberInput' => [$ns . 'Article\\Command\\AddInventoryNumber', ['articleId', 'networkInterfaces'], [], 'New inventory number (the number is assigned automatically)', false],
            'InventoryNumberPatch' => [$ns . 'Article\\Command\\UpdateInventoryNumber', ['id', 'networkInterfaces', 'updateCurrentValue'], [], 'Update inventory number (only the fields sent)', true],
            'ArticleSetInput' => [$ns . 'ArticleSet\\Command\\CreateArticleSet', [], [], 'New set', false],
            'ArticleSetPatch' => [$ns . 'ArticleSet\\Command\\UpdateArticleSet', ['id'], [], 'Update set (only the fields sent)', true],
            'CategoryInput' => [$ns . 'Category\\Command\\CreateCategory', [], [], 'New category', false],
            'CategoryPatch' => [$ns . 'Category\\Command\\UpdateCategory', ['id'], [], 'Update category', true],
            'ManufacturerInput' => [$ns . 'Manufacturer\\Command\\CreateManufacturer', [], [], 'New manufacturer', false],
            'ManufacturerPatch' => [$ns . 'Manufacturer\\Command\\UpdateManufacturer', ['id'], [], 'Update manufacturer', true],
            'LaborTimeArticleInput' => [$ns . 'LaborTime\\Command\\CreateLaborTimeArticle', [], [], 'New labor time article', false],
            'LaborTimeArticlePatch' => [$ns . 'LaborTime\\Command\\UpdateLaborTimeArticle', ['id'], [], 'Update labor time article', true],
            'SupplierInput' => [$ns . 'Supplier\\Command\\CreateSupplier', [], [], 'New supplier', false],
            'SupplierPatch' => [$ns . 'Supplier\\Command\\UpdateSupplier', ['id'], [], 'Update supplier', true],
            'VenueInput' => [$ns . 'Venue\\Command\\CreateVenue', [], [], 'New venue', false],
            'VenuePatch' => [$ns . 'Venue\\Command\\UpdateVenue', ['id'], [], 'Update venue', true],
            'WarehouseInput' => [$ns . 'Warehouse\\Command\\CreateWarehouse', [], [], 'New warehouse', false],
            'WarehousePatch' => [$ns . 'Warehouse\\Command\\UpdateWarehouse', ['id'], [], 'Update warehouse', true],
            'VehicleInput' => [$ns . 'Vehicle\\Command\\CreateVehicle', [], [], 'New vehicle', false],
            'VehiclePatch' => [$ns . 'Vehicle\\Command\\UpdateVehicle', ['id'], [], 'Update vehicle', true],
            'RentalInput' => [$ns . 'Rental\\Command\\CreateRental', [], [], 'New event', false],
            'RentalPatch' => [$ns . 'Rental\\Command\\UpdateRental', ['id'], [], 'Update event (only the fields sent)', true],
            'RentalLogisticsPatch' => [$ns . 'Rental\\Command\\UpdateRentalLogistics', ['rentalId'], [], 'Update pickup/return (only the fields sent)', true],
            'RentalItemInput' => [$ns . 'Rental\\Command\\AddRentalItem', ['rentalId'], [], 'Schedule article', false],
            'RentalItemPatch' => [$ns . 'Rental\\Command\\UpdateRentalItem', ['id'], [], 'Change quantity', false],
            'OfferInput' => [$ns . 'Offer\\Command\\CreateOffer', [], [], 'New offer (draft)', false],
            'OfferPatch' => [$ns . 'Offer\\Command\\UpdateOffer', ['id'], [], 'Update offer (item lists not sent remain unchanged)', true],
            'OrderConfirmationInput' => [$ns . 'OrderConfirmation\\Command\\CreateOrderConfirmation', [], [], 'New order confirmation', false],
            'OrderConfirmationPatch' => [$ns . 'OrderConfirmation\\Command\\UpdateOrderConfirmation', ['id'], [], 'Update order confirmation', true],
            'InvoiceInput' => [$ns . 'Invoice\\Command\\CreateInvoice', [], [], 'New invoice as draft (no invoice number yet)', false],
            'InvoicePatch' => [$ns . 'Invoice\\Command\\UpdateInvoice', ['id'], [], 'Update invoice draft', true],
            'EmailInput' => [$ns . 'Invoice\\Command\\EmailInvoicePdf', ['invoiceId'], [], 'Send e-mail to the customer', false],
            'DunningEmailInput' => [$ns . 'Dunning\\Command\\EmailDunningNotice', ['dunningNoticeId'], [], 'Send dunning notice by e-mail', false],
            'ReceiptInput' => [$ns . 'Receipt\\Command\\CreateReceipt', ['uploadedFile'], ['rawAmount' => 'amount', 'rawReceiptDate' => 'receipt_date'], 'New receipt (multipart/form-data, file in the field "file")', false],
            'ReceiptPatch' => [$ns . 'Receipt\\Command\\UpdateReceipt', ['id', 'uploadedFile'], ['rawAmount' => 'amount', 'rawReceiptDate' => 'receipt_date'], 'Update receipt (only the fields sent)', true],
            'PurchaseOrderInput' => [$ns . 'Purchase\\Command\\CreatePurchaseOrder', [], [], 'New purchase order', false],
            'PurchaseOrderPatch' => [$ns . 'Purchase\\Command\\UpdatePurchaseOrder', ['id'], [], 'Update purchase order', true],
            'ReceiveInput' => [$ns . 'Purchase\\Command\\ReceivePurchaseOrderItem', ['purchaseOrderItemId'], [], 'Book goods receipt', false],
            'ElectricalInspectionInput' => [$ns . 'ElectricalInspection\\Command\\CreateElectricalInspection', ['inventoryNumberId'], [], 'Record electrical inspection', false],
        ];
    }

    /**
     * Item schemas for array parameters ("Class::param" => schema in common.yaml).
     *
     * @return array<string, string>
     */
    private static function arrayItems(): array
    {
        $doc = ['items' => 'DocumentItemRow', 'laborItems' => 'LaborItemRow', 'saleItems' => 'SaleItemRow', 'freeTextItems' => 'FreeTextItemRow', 'sectionTexts' => 'SectionTextRow'];
        $map = [
            'CreateCustomer::contacts' => 'ContactRow', 'UpdateCustomer::contacts' => 'ContactRow',
            'CreateArticle::bundleComponents' => 'BundleComponentRow', 'UpdateArticle::bundleComponents' => 'BundleComponentRow',
            'CreateArticleSet::items' => 'SetItemRow', 'UpdateArticleSet::items' => 'SetItemRow',
            'CreateArticleSet::rules' => 'SetRuleRow', 'UpdateArticleSet::rules' => 'SetRuleRow',
            'CreateArticleSet::upgrades' => 'SetUpgradeRow', 'UpdateArticleSet::upgrades' => 'SetUpgradeRow',
            'CreatePurchaseOrder::items' => 'PurchaseOrderItemRow', 'UpdatePurchaseOrder::items' => 'PurchaseOrderItemRow',
        ];
        foreach (['CreateOffer', 'UpdateOffer', 'CreateOrderConfirmation', 'UpdateOrderConfirmation', 'CreateInvoice', 'UpdateInvoice'] as $command) {
            foreach ($doc as $param => $schema) {
                $map[$command . '::' . $param] = $schema;
            }
        }

        return $map;
    }

    /**
     * String command parameters that accept the values of a domain enum ("Class::param" or "*::param").
     *
     * @return array<string, class-string<BackedEnum>>
     */
    private static function enumHints(): array
    {
        return [
            'CreateCustomer::type' => \App\Domain\Customer\CustomerType::class,
            'UpdateCustomer::type' => \App\Domain\Customer\CustomerType::class,
            'CreateSupplier::type' => \App\Domain\Supplier\SupplierType::class,
            'UpdateSupplier::type' => \App\Domain\Supplier\SupplierType::class,
            'CreateVehicle::category' => \App\Domain\Vehicle\VehicleCategory::class,
            'UpdateVehicle::category' => \App\Domain\Vehicle\VehicleCategory::class,
            'CreateReceipt::category' => \App\Domain\Receipt\ReceiptCategory::class,
            'UpdateReceipt::category' => \App\Domain\Receipt\ReceiptCategory::class,
            'CreateVenue::fogPolicy' => \App\Domain\Venue\FogPolicy::class,
            'UpdateVenue::fogPolicy' => \App\Domain\Venue\FogPolicy::class,
            '*::protectionClass' => \App\Domain\Article\ProtectionClass::class,
            '*::rackWidth' => \App\Domain\Rack\RackWidth::class,
        ];
    }

    /** @return array<string, mixed> OpenAPI "components/schemas" map */
    public static function schemas(): array
    {
        $schemas = [];
        foreach (self::entities() as $name => [$class, $exclude, $description]) {
            $schemas[$name] = self::schemaFor($class, $exclude, $description);
        }
        foreach (self::inputs() as $name => [$class, $exclude, $renames, $description, $optional]) {
            $schemas[$name] = self::inputFor($class, $exclude, $renames, $description, $optional);
        }

        return $schemas;
    }

    /**
     * @param class-string $class
     * @param list<string> $exclude
     * @param array<string, string> $renames
     * @return array<string, mixed>
     */
    private static function inputFor(string $class, array $exclude, array $renames, string $description, bool $optional): array
    {
        $short = (new ReflectionClass($class))->getShortName();
        $items = self::arrayItems();
        $properties = [];
        $required = [];
        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $param) {
            $name = $param->getName();
            if (in_array($name, $exclude, true)) {
                continue;
            }
            $field = $renames[$name] ?? Normalizer::snake($name);
            $type = $param->getType();
            $schema = $type instanceof ReflectionNamedType ? self::namedType($type->getName(), $name, $type->allowsNull()) : ['type' => 'string'];
            if (($schema['type'] ?? '') === 'array' && isset($items[$short . '::' . $name])) {
                $schema['items'] = ['$ref' => 'common.yaml#/schemas/' . $items[$short . '::' . $name]];
            }
            $enum = self::enumHints()[$short . '::' . $name] ?? self::enumHints()['*::' . $name] ?? null;
            if ($enum !== null && ($schema['type'] ?? '') === 'string') {
                $schema['enum'] = array_merge(array_map(static fn (BackedEnum $c) => $c->value, $enum::cases()), ($schema['nullable'] ?? false) ? [null] : []);
            }
            if ($param->isDefaultValueAvailable() && is_scalar($param->getDefaultValue())) {
                $schema['default'] = $param->getDefaultValue();
            }
            $properties[$field] = $schema;
            if (!$optional && !$param->isDefaultValueAvailable() && !$param->allowsNull()) {
                $required[] = $field;
            }
        }
        $schema = ['type' => 'object', 'description' => $description];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        $schema['properties'] = $properties;

        return $schema;
    }

    public static function yaml(): string
    {
        return "# GENERATED by bin/generate-api-schemas.php from the domain classes — do not edit by hand.\n"
            . "# Field lists of the entities returned by the REST API (see Normalizer).\n"
            . self::emit(self::schemas(), 0);
    }

    /**
     * @param class-string $class
     * @param list<string> $exclude
     * @return array<string, mixed>
     */
    private static function schemaFor(string $class, array $exclude, string $description): array
    {
        $properties = [];
        $required = [];
        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($property->isStatic() || in_array($name, $exclude, true) || self::isSecret($name)) {
                continue;
            }
            $properties[Normalizer::snake($name)] = self::typeFor($property);
            $required[] = Normalizer::snake($name);
        }

        return ['type' => 'object', 'description' => $description, 'required' => $required, 'properties' => $properties];
    }

    private static function isSecret(string $name): bool
    {
        $reflection = new \ReflectionClassConstant(Normalizer::class, 'SECRET_PROPERTIES');

        return in_array($name, (array) $reflection->getValue(), true);
    }

    /** @return array<string, mixed> */
    private static function typeFor(ReflectionProperty $property): array
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return [];
        }

        return self::namedType($type->getName(), $property->getName(), $type->allowsNull());
    }

    /** @return array<string, mixed> */
    private static function namedType(string $name, string $propertyName, bool $allowsNull): array
    {
        $nullable = $allowsNull && $name !== 'mixed';
        $schema = match (true) {
            $name === 'string' => ['type' => 'string'],
            $name === 'int' => ['type' => 'integer'],
            $name === 'float' => ['type' => 'number'],
            $name === 'bool' => ['type' => 'boolean'],
            $name === 'array' => ['type' => 'array', 'items' => (object) []],
            is_a($name, DateTimeInterface::class, true) => ['type' => 'string', 'format' => Normalizer::isDateField($propertyName) ? 'date' : 'date-time'],
            is_subclass_of($name, BackedEnum::class) => ['type' => is_int($name::cases()[0]->value ?? '') ? 'integer' : 'string', 'enum' => array_map(static fn (BackedEnum $c) => $c->value, $name::cases())],
            default => ['type' => 'object'],
        };
        if ($nullable) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /** Minimal YAML emitter for nested arrays of scalars (block style). */
    private static function emit(mixed $value, int $indent): string
    {
        $pad = str_repeat('  ', $indent);
        $out = '';
        if (is_array($value) && array_is_list($value) && $value !== []) {
            foreach ($value as $item) {
                $out .= $pad . '- ' . self::scalar($item) . "\n";
            }

            return $out;
        }
        foreach ((array) $value as $key => $item) {
            if ($item instanceof \stdClass || $item === []) {
                $out .= $pad . $key . ": {}\n";
            } elseif (is_array($item)) {
                $out .= $pad . $key . ":\n" . self::emit($item, $indent + 1);
            } else {
                $out .= $pad . $key . ': ' . self::scalar($item) . "\n";
            }
        }

        return $out;
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
