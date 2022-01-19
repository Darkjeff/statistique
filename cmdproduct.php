<?php
/* Copyright (C) 2018       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *        \file       htdocs/compta/stats/byratecountry.php
 *        \brief      VAT by rate
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/report.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/tax.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/tva/class/tva.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/localtax/class/localtax.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
require_once DOL_DOCUMENT_ROOT . '/expensereport/class/paymentexpensereport.class.php';

// Load translation files required by the page
$langs->loadLangs(array("other", "compta", "banks", "bills", "companies", "product", "trips", "admin", "accountancy"));

$modecompta = GETPOST('modecompta', 'alpha');

// Date range
$year = GETPOST("year", 'int');
$month = GETPOST("month", 'int');
if (empty($year)) {
	$year_current = strftime("%Y", dol_now());
	$month_current = strftime("%m", dol_now());
	$year_start = $year_current;
} else {
	$year_current = $year;
	$month_current = strftime("%m", dol_now());
	$year_start = $year;
}
$date_start = dol_mktime(0, 0, 0, GETPOST("date_startmonth"), GETPOST("date_startday"), GETPOST("date_startyear"));
$date_end = dol_mktime(23, 59, 59, GETPOST("date_endmonth"), GETPOST("date_endday"), GETPOST("date_endyear"));
$companyid = GETPOST('companyid', 'int');
$prodid = GETPOST('prodid', 'int');

// Quarter
if (empty($date_start) || empty($date_end)) { // We define date_start and date_end
	$q = GETPOST("q", "int");
	if (empty($q)) {
		// We define date_start and date_end
		$month_start = GETPOST("month") ? GETPOST("month") : ($conf->global->SOCIETE_FISCAL_MONTH_START ? ($conf->global->SOCIETE_FISCAL_MONTH_START) : 1);
		$year_end = $year_start;
		$month_end = $month_start;
		if (!GETPOST("month")) {    // If month not forced
			if (!GETPOST('year') && $month_start > $month_current) {
				$year_start--;
				$year_end--;
			}
			$month_end = $month_start - 1;
			if ($month_end < 1) $month_end = 12;
			else $year_end++;
		}
		$date_start = dol_get_first_day($year_start, $month_start, false);
		$date_end = dol_get_last_day($year_end, $month_end, false);
	} else {
		if ($q == 1) {
			$date_start = dol_get_first_day($year_start, 1, false);
			$date_end = dol_get_last_day($year_start, 3, false);
		}
		if ($q == 2) {
			$date_start = dol_get_first_day($year_start, 4, false);
			$date_end = dol_get_last_day($year_start, 6, false);
		}
		if ($q == 3) {
			$date_start = dol_get_first_day($year_start, 7, false);
			$date_end = dol_get_last_day($year_start, 9, false);
		}
		if ($q == 4) {
			$date_start = dol_get_first_day($year_start, 10, false);
			$date_end = dol_get_last_day($year_start, 12, false);
		}
	}
}

// $date_start and $date_end are defined. We force $year_start and $nbofyear
$tmps = dol_getdate($date_start);
$year_start = $tmps['year'];
$tmpe = dol_getdate($date_end);
$year_end = $tmpe['year'];

$tmp_date_end = dol_time_plus_duree($date_start, 1, 'y') - 1;
if ($tmp_date_end < $date_end || $date_end < $date_start) $date_end = $tmp_date_end;

$min = price2num(GETPOST("min", "alpha"));
if (empty($min)) $min = 0;

// Define modetax (0 or 1)
// 0=normal, 1=option vat for services is on debit, 2=option on payments for products
$modetax = $conf->global->TAX_MODE;
if (GETPOSTISSET("modetax")) $modetax = GETPOST("modetax", 'int');
if (empty($modetax)) $modetax = 0;

// Security check
$socid = GETPOST('socid', 'int');
if ($user->societe_id) $socid = $user->societe_id;
$result = restrictedArea($user, 'tax', '', '', 'charges');


/*
 * View
 */

$form = new Form($db);
$company_static = new Societe($db);
$invoice_customer = new Facture($db);
$invoice_supplier = new FactureFournisseur($db);
$expensereport = new ExpenseReport($db);
$product_static = new Product($db);
$payment_static = new Paiement($db);
$paymentfourn_static = new PaiementFourn($db);
$paymentexpensereport_static = new PaymentExpenseReport($db);

$morequerystring = '';
$listofparams = array('date_startmonth', 'date_startyear', 'date_startday', 'date_endmonth', 'date_endyear',
					  'date_endday');
foreach ($listofparams as $param) {
	if (GETPOST($param) != '') $morequerystring .= ($morequerystring ? '&' : '') . $param . '=' . GETPOST($param);
}

llxHeader('', $langs->trans("TurnoverReport"), '', '', 0, 0, '', '', $morequerystring);


$fsearch = '  <input type="hidden" name="year" value="' . $year . '">';
$fsearch .= '  <input type="hidden" name="modetax" value="' . $modetax . '">';

// Show report header
$name = $langs->trans("xxx");
$calcmode = '';
if ($modetax == 0) $calcmode = $langs->trans('OptionVATDefault');
if ($modetax == 1) $calcmode = $langs->trans('OptionVATDebitOption');
if ($modetax == 2) $calcmode = $langs->trans('OptionPaymentForProductAndServices');
$calcmode .= '<br>(' . $langs->trans("TaxModuleSetupToModifyRules", DOL_URL_ROOT . '/admin/taxes.php') . ')';
// Set period
$period = $form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0) . ' - ' . $form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0);
$prevyear = $year_start;
$prevquarter = $q;
if ($prevquarter > 1) {
	$prevquarter--;
} else {
	$prevquarter = 4;
	$prevyear--;
}
$nextyear = $year_start;
$nextquarter = $q;
if ($nextquarter < 4) {
	$nextquarter++;
} else {
	$nextquarter = 1;
	$nextyear++;
}
$description .= $fsearch;
$builddate = dol_now();

if ($conf->global->TAX_MODE_SELL_PRODUCT == 'invoice') $description .= $langs->trans("RulesVATDueProducts");
if ($conf->global->TAX_MODE_SELL_PRODUCT == 'payment') $description .= $langs->trans("RulesVATInProducts");
if ($conf->global->TAX_MODE_SELL_SERVICE == 'invoice') $description .= '<br>' . $langs->trans("RulesVATDueServices");
if ($conf->global->TAX_MODE_SELL_SERVICE == 'payment') $description .= '<br>' . $langs->trans("RulesVATInServices");
if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
	$description .= '<br>' . $langs->trans("DepositsAreNotIncluded");
}
if (!empty($conf->global->MAIN_MODULE_ACCOUNTING)) $description .= '<br>' . $langs->trans("ThisIsAnEstimatedValue");

// Customers invoices
$elementcust = $langs->trans("CustomersInvoices");
$productcust = $langs->trans("ProductOrService");
$amountcust = $langs->trans("AmountHT");

// Suppliers invoices
$elementsup = $langs->trans("SuppliersInvoices");
$productsup = $productcust;
$amountsup = $amountcust;
$namesup = $namecust;


// TODO Report from bookkeeping not yet available, so we switch on report on business events
if ($modecompta == "BOOKKEEPING") $modecompta = "CREANCES-DETTES";
if ($modecompta == "BOOKKEEPINGCOLLECTED") $modecompta = "RECETTES-DEPENSES";

// Show report header
if ($modecompta == "CREANCES-DETTES") {
	$name = $langs->trans("Turnover") . ', ' . $langs->trans("ByVatRate");
	$calcmode = $langs->trans("CalcModeDebt");
	//$calcmode.='<br>('.$langs->trans("SeeReportInInputOutputMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year_start.'&modecompta=RECETTES-DEPENSES">','</a>').')';

	$description = $langs->trans("RulesCADue");
	if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$description .= $langs->trans("DepositsAreNotIncluded");
	} else {
		$description .= $langs->trans("DepositsAreIncluded");
	}

	$builddate = dol_now();
} elseif ($modecompta == "RECETTES-DEPENSES") {
	$name = $langs->trans("TurnoverCollected") . ', ' . $langs->trans("ByVatRate");
	$calcmode = $langs->trans("CalcModeEngagement");
	//$calcmode.='<br>('.$langs->trans("SeeReportInDueDebtMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year_start.'&modecompta=CREANCES-DETTES">','</a>').')';

	$description = $langs->trans("RulesCAIn");
	$description .= $langs->trans("DepositsAreIncluded");

	$builddate = dol_now();
}
$period = $form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0) . ' - ' . $form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0);
$client = '<br />' . $langs->trans('Customer') . $form->select_company($companyid, 'companyid', '', 1, 0, 0, array(), 0);
$product = '<br />' . $langs->trans('Product') . $form->select_produits($prodid, 'prodid', '', 0, 0, 1, 2, '', 0, array(), 0, 1, 0, '', 0, '', array(), 1);
if ($date_end == dol_time_plus_duree($date_start, 1, 'y') - 1) $periodlink = '<a href="' . $_SERVER["PHP_SELF"] . '?year=' . ($year_start - 1) . '&modecompta=' . $modecompta . '">' . img_previous() . '</a> <a href="' . $_SERVER["PHP_SELF"] . '?year=' . ($year_start + 1) . '&modecompta=' . $modecompta . '">' . img_next() . '</a>';
else $periodlink = '';

$description .= '  <input type="hidden" name="modecompta" value="' . $modecompta . '">';

report_header($name, '', $period . $client . $product, $periodlink, $description, $builddate, '', array(), $calcmode);

if (!empty($conf->accounting->enabled) && $modecompta != 'BOOKKEEPING') {
	print info_admin($langs->trans("WarningReportNotReliable"), 0, 0, 1);
}

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td width="6%" class="right">' . $langs->trans("ref") . '</td>';
print '<td align="left">' . $langs->trans("Product") . '</td>';
print '<td align="left">' . $langs->trans("Customer") . '</td>';
$i = 0;
while ($i < 12) {
	$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
	if ($j > 12) $j -= 12;
	print '<td width="60" align="right">' . $langs->trans('MonthShort' . str_pad($j, 2, '0', STR_PAD_LEFT)) . '</td>';
	$i++;
}
print '<td width="60" align="right"><b>' . $langs->trans("Quantity") . '</b></td></tr>';

$sql = "SELECT p.ref AS refproduct,";
$sql .= " p.label AS product_label,";
$sql .= " soc.nom AS customer,";
for ($i = 1; $i <= 12; $i++) {
	$sql .= " SUM(" . $db->ifsql('MONTH(f.date_commande)=' . $i, 'fd.qty', '0') . ") AS month" . str_pad($i, 2, '0', STR_PAD_LEFT) . ",";
}
$sql .= "  SUM(fd.total_ht) as total";
$sql .= " FROM " . MAIN_DB_PREFIX . "commandedet as fd";
$sql .= "  INNER JOIN " . MAIN_DB_PREFIX . "commande as f ON f.rowid = fd.fk_commande";
$sql .= "  INNER JOIN " . MAIN_DB_PREFIX . "societe as soc ON soc.rowid = f.fk_soc";
$sql .= "  INNER JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = fd.fk_product";
$sql .= "  LEFT JOIN " . MAIN_DB_PREFIX . "c_country as cc ON cc.rowid = soc.fk_pays";
$sql .= " WHERE f.date_commande >= '" . $db->idate($date_start) . "'";
$sql .= "  AND f.date_commande <= '" . $db->idate($date_end) . "'";
$sql .= " AND f.fk_statut in (1,2)";
if (!empty($companyid)) {
	$sql .= " AND f.fk_soc=" . (int) $companyid;
}
if (!empty($prodid)) {
	$sql .= " AND fd.fk_product=" . (int) $prodid;
}
$sql .= " GROUP BY soc.nom, p.ref";

dol_syslog("htdocs/compta/tva/index.php sql=" . $sql, LOG_DEBUG);
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$totalpermonth = array();
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven"><td class="right">' . $obj->refproduct . '</td>';
		print '<td align="left">' . $obj->product_label . '</td>';

		print '<td>' . $obj->customer . '</td>';
		for ($i = 0; $i < 12; $i++) {
			$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
			if ($j > 12) $j -= 12;
			$monthj = 'month' . str_pad($j, 2, '0', STR_PAD_LEFT);
			print '<td align="right" width="6%">' . price($obj->$monthj) . '</td>';
			$totalpermonth[$j] = (empty($totalpermonth[$j]) ? 0 : $totalpermonth[$j]) + $obj->$monthj;
		}
		print '<td align="right" width="6%"><b>' . price($obj->total) . '</b></td>';
		$totalpermonth['total'] = (empty($totalpermonth['total']) ? 0 : $totalpermonth['total']) + $obj->total;
		print '</tr>';
	}
	$db->free($resql);

	// Total
	print '<tr class="liste_total"><td class="right"></td>';
	print '<td align="left"></td>';
	print '<td></td>';
	for ($i = 0; $i < 12; $i++) {
		$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
		if ($j > 12) $j -= 12;
		$monthj = 'month' . str_pad($j, 2, '0', STR_PAD_LEFT);
		print '<td align="right" width="6%">' . price($totalpermonth[$j]) . '</td>';
	}
	print '<td align="right" width="6%"><b>' . price($totalpermonth['total']) . '</b></td>';
	print '</tr>';
} else {
	print $db->lasterror(); // Show last sql error
}

/*
print '<tr class="liste_titre"><td width="6%" class="right">' . $langs->trans("PurchasebyVatrate") . '</td>';
print '<td align="left">' . $langs->trans("ProductOrService") . '</td>';
print '<td align="left">' . $langs->trans("Country") . '</td>';
$i = 0;
while ($i < 12) {
	$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
	if ($j > 12) $j -= 12;
	print '<td width="60" align="right">' . $langs->trans('MonthShort' . str_pad($j, 2, '0', STR_PAD_LEFT)) . '</td>';
	$i++;
}
print '<td width="60" align="right"><b>' . $langs->trans("TotalHT") . '</b></td></tr>';

$sql2 = "SELECT ffd.tva_tx AS vatrate,";
$sql2 .= " ffd.product_type AS product_type,";
$sql2 .= " cc.label AS country,";
for ($i = 1; $i <= 12; $i++) {
	$sql2 .= " SUM(" . $db->ifsql('MONTH(ff.datef)=' . $i, 'ffd.total_ht', '0') . ") AS month" . str_pad($i, 2, '0', STR_PAD_LEFT) . ",";
}
$sql2 .= "  SUM(ffd.total_ht) as total";
$sql2 .= " FROM " . MAIN_DB_PREFIX . "facture_fourn_det as ffd";
$sql2 .= "  INNER JOIN " . MAIN_DB_PREFIX . "facture_fourn as ff ON ff.rowid = ffd.fk_facture_fourn";
$sql2 .= "  INNER JOIN " . MAIN_DB_PREFIX . "societe as soc ON soc.rowid = ff.fk_soc";
$sql2 .= "  LEFT JOIN " . MAIN_DB_PREFIX . "c_country as cc ON cc.rowid = soc.fk_pays";
$sql2 .= " WHERE ff.datef >= '" . $db->idate($date_start) . "'";
$sql2 .= "  AND ff.datef <= '" . $db->idate($date_end) . "'";
$sql .= " AND ff.fk_statut in (1,2)";
if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
	$sql .= " AND ff.type IN (0,1,2,5)";
} else {
	$sql .= " AND ff.type IN (0,1,2,3,5)";
}
$sql2 .= " AND ff.entity IN (" . getEntity("facture_fourn", 0) . ")";
$sql2 .= " GROUP BY ffd.tva_tx, ffd.product_type, cc.label";

//print $sql2;
dol_syslog("htdocs/compta/tva/index.php sql=" . $sql, LOG_DEBUG);
$resql2 = $db->query($sql2);
if ($resql2) {
	$num = $db->num_rows($resql2);
	$totalpermonth = array();
	while ($obj = $db->fetch_object($resql2)) {
		print '<tr class="oddeven"><td class="right">' . vatrate($obj->vatrate) . '</td>';
		if ($obj->product_type == 0) {
			print '<td align="left">' . $langs->trans("Product") . '</td>';
		} else {
			print '<td align="left">' . $langs->trans("Service") . '</td>';
		}
		print '<td>' . $obj->country . '</td>';
		for ($i = 0; $i < 12; $i++) {
			$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
			if ($j > 12) $j -= 12;
			$monthj = 'month' . str_pad($j, 2, '0', STR_PAD_LEFT);
			print '<td align="right" width="6%">' . price($obj->$monthj) . '</td>';
			$totalpermonth[$j] = (empty($totalpermonth[$j]) ? 0 : $totalpermonth[$j]) + $obj->$monthj;
		}
		print '<td align="right" width="6%"><b>' . price($obj->total) . '</b></td>';
		$totalpermonth['total'] = (empty($totalpermonth['total']) ? 0 : $totalpermonth['total']) + $obj->total;
		print '</tr>';
	}
	$db->free($resql2);

	// Total
	print '<tr class="liste_total"><td class="right"></td>';
	print '<td align="left"></td>';
	print '<td></td>';
	for ($i = 0; $i < 12; $i++) {
		$j = $i + (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 1 : $conf->global->SOCIETE_FISCAL_MONTH_START);
		if ($j > 12) $j -= 12;
		$monthj = 'month' . str_pad($j, 2, '0', STR_PAD_LEFT);
		print '<td align="right" width="6%">' . price($totalpermonth[$j]) . '</td>';
	}
	print '<td align="right" width="6%"><b>' . price($totalpermonth['total']) . '</b></td>';
	print '</tr>';
} else {
	print $db->lasterror(); // Show last sql error
}*/
print "</table>\n";


// End of page
llxFooter();
$db->close();
