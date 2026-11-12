<?php

declare(strict_types=1);

namespace App\Domain\Datev;

use App\Domain\Receipt\ReceiptCategory;

/** Sachkonten, die der DATEV-Export bebucht (siehe DatevChartOfAccounts::defaultAccount()). */
enum DatevAccountRole: string
{
    case Revenue19 = 'revenue_19';
    case Revenue7 = 'revenue_7';
    case RevenueTaxFree = 'revenue_tax_free';
    case RevenueSmallBusiness = 'revenue_small_business';
    case ExpenseGoods = 'expense_goods';
    case ExpenseTools = 'expense_tools';
    case ExpenseVehicle = 'expense_vehicle';
    case ExpenseOther = 'expense_other';

    public function label(): string
    {
        return match ($this) {
            self::Revenue19 => 'Erlöse 19 % USt',
            self::Revenue7 => 'Erlöse 7 % USt',
            self::RevenueTaxFree => 'Erlöse steuerfrei (0 %)',
            self::RevenueSmallBusiness => 'Erlöse Kleinunternehmer (§ 19 UStG)',
            self::ExpenseGoods => 'Belege: Wareneinkauf',
            self::ExpenseTools => 'Belege: Werkzeug',
            self::ExpenseVehicle => 'Belege: Fahrzeug',
            self::ExpenseOther => 'Belege: Sonstiges',
        };
    }

    public static function forReceiptCategory(ReceiptCategory $category): self
    {
        return match ($category) {
            ReceiptCategory::Wareneinkauf => self::ExpenseGoods,
            ReceiptCategory::Werkzeug => self::ExpenseTools,
            ReceiptCategory::Fahrzeug => self::ExpenseVehicle,
            ReceiptCategory::Sonstiges => self::ExpenseOther,
        };
    }

    /** @return self[] */
    public static function revenueRoles(): array
    {
        return [self::Revenue19, self::Revenue7, self::RevenueTaxFree, self::RevenueSmallBusiness];
    }

    /** @return self[] */
    public static function expenseRoles(): array
    {
        return [self::ExpenseGoods, self::ExpenseTools, self::ExpenseVehicle, self::ExpenseOther];
    }
}
