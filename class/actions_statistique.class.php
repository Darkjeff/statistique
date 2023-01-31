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
			if ($object->id > 0 && $action=='edit') {
				$id_tag_plateform = $conf->global->STATISTIQUE_TAG_PLATFORME;
				global $extrafields;
				$new_param = str_replace('__STATISTIQUE_TAG_PLATFORME__',(int)$id_tag_plateform,key($extrafields->attributes[$object->table_element]['param']['fk_soc_platform']['options']));
				$extrafields->attributes[$object->table_element]['param']['fk_soc_platform']['options']=array($new_param=>null);
			}
		}
	}

		/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;
		$currentcontext = explode(':', $parameters['context']);
		if (in_array('thirdpartycard', $currentcontext) || in_array('suppliercard', $currentcontext)) {
			print '
			<script type="text/javascript">
				$(document).ready(function() {
                    $(\'a[href*="action=edit_extras&attribute=fk_soc_platform\"]\').hide();
				});
			</script>';
		}
	}
}
