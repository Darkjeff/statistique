<?php
/* Copyright (C) 2022 Florian HENRY <florian.henry@scopen.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolipad/class/actions_dolipad.class.php
 * \ingroup dolipad
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsDolipad
 */
class ActionsStatistique
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the showOptionals function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function showOptionals($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;
		$currentcontext = explode(':', $parameters['context']);
		if (in_array('thirdpartycard', $currentcontext) && !empty($action)) {
			if ($object->id > 0 && $action == 'edit') {
				$id_tag_plateform = $conf->global->STATISTIQUE_TAG_PLATFORME;
				global $extrafields;
				$new_param = str_replace('__STATISTIQUE_TAG_PLATFORME__', (int)$id_tag_plateform, key($extrafields->attributes[$object->table_element]['param']['fk_soc_platform']['options']));
				$extrafields->attributes[$object->table_element]['param']['fk_soc_platform']['options'] = array($new_param => null);
			}
		}
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;
		$currentcontext = explode(':', $parameters['context']);
		if (in_array('thirdpartycard', $currentcontext)
			|| in_array('suppliercard', $currentcontext)
			|| in_array('thirdpartycomm', $currentcontext)) {
			print '
			<script type="text/javascript">
				$(document).ready(function() {
                    $(\'a[href*="action=edit_extras&attribute=fk_soc_platform\"]\').hide();
				});
			</script>';
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('orderlist'))) {
			$label = img_picto('', 'pdf', 'class="pictofixedwidth"') . $langs->trans("PDF Etiquette Palette ");
			//$this->resprints = '<option value="pdf_palette" data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadatas (context, etc...)
	 * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('orderlist')) && $action == 'pdf_palette') {
			$diroutputmassaction = $conf->commande->multidir_output[$conf->entity].'/temp/massgeneration/'.$user->id;
			$uploaddir = $conf->commande->multidir_output[$conf->entity];
			if (empty($diroutputmassaction)) {
				dol_print_error(null, 'include of actions_massactions.inc.php is done but var $diroutputmassaction was not defined');
				exit;
			}

			$toselect = GETPOST('toselect', 'array');
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

			$objecttmp = new Commande($db);
			$listofobjectid = array();
			$listofobjectthirdparties = array();
			$listofobjectref = array();
			foreach ($toselect as $toselectid) {
				$objecttmp = new Commande($db); // must create new instance because instance is saved into $listofobjectref array for future use
				$result = $objecttmp->fetch($toselectid);
				if ($result > 0) {
					$listofobjectid[$toselectid] = $toselectid;
					$thirdpartyid = $objecttmp->fk_soc ? $objecttmp->fk_soc : $objecttmp->socid;
					$listofobjectthirdparties[$thirdpartyid] = $thirdpartyid;
					$listofobjectref[$toselectid] = $objecttmp->ref;
				}
			}

			$arrayofinclusion = array();
			foreach ($listofobjectref as $tmppdf) {
				$arrayofinclusion[] = '^' . preg_quote(dol_sanitizeFileName($tmppdf), '/') . '\.pdf$';
			}
			foreach ($listofobjectref as $tmppdf) {
				$arrayofinclusion[] = '^' . preg_quote(dol_sanitizeFileName($tmppdf), '/') . '_[a-zA-Z0-9\-\_\']+\.pdf$'; // To include PDF generated from ODX files
			}
			$listoffiles = dol_dir_list($uploaddir, 'all', 1, implode('|', $arrayofinclusion), '\.meta$|\.png', 'date', SORT_DESC, 0, true);

			// build list of files with full path
			$files = array();
			foreach ($listofobjectref as $basename) {
				$basename = dol_sanitizeFileName($basename);
				foreach ($listoffiles as $filefound) {
					if (strstr($filefound["name"], $basename)) {
						$files[] = $uploaddir . '/' . $basename . '/' . $filefound["name"];
						break;
					}
				}
			}

			// Define output language (Here it is not used because we do only merging existing PDF)
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
				$newlang = GETPOST('lang_id', 'aZ09');
			}
			//elseif ($conf->global->MAIN_MULTILANGS && empty($newlang) && is_object($objecttmp->thirdparty)) {		// On massaction, we can have several values for $objecttmp->thirdparty
			//	$newlang = $objecttmp->thirdparty->default_lang;
			//}
			if (!empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}
		}
	}
}
