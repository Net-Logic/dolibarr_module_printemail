<?php
/*
 * Copyright (C) 2014-2023  Frederic France      <frederic.france@free.fr>
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
 * or see http://www.gnu.org/
 */

/**
 *      \file       htdocs/core/modules/printing/printemail.modules.php
 *      \ingroup    printing
 *      \brief      File to provide printing with Email
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/printing/modules_printing.php';

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
/**
 *   Class to provide printing with Email
 */
class printing_printemail extends PrintingDriver
{
	// phpcs:enable
	public $name = 'printemail';
	public $desc = 'PrintEmailDesc';
	public $picto = 'printer';
	public $active = 'PRINTING_PRINTEMAIL';
	public $conf = [];
	public $email;
	public $printername;
	public $error;
	public $errors = [];

	/**
	 * @var string html string
	 */
	public $resprint;

	/**
	 * @var DoliDB
	 */
	public $db;
	public const LANGFILE = 'printemail@printemail';

	/**
	 *  Constructor
	 *
	 *  @param      DoliDB      $db      Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->email = getDolGlobalString('PRINTEMAIL_EMAIL');
		$this->printername = getDolGlobalString('PRINTEMAIL_PRINTERNAME');
		$this->conf[] = [
			'varname' => 'PRINTEMAIL_EMAIL',
			'required' => 1,
			'example' => 'someone@somedomain.com',
			'type' => 'text',
			'moreattributes' => 'autocomplete="off"',
		];
		$this->conf[] = [
			'varname' => 'PRINTEMAIL_PRINTERNAME',
			'required' => 1,
			'example' => 'Printer Name',
			'type' => 'text',
		];
		$this->conf[] = ['enabled' => 1, 'type' => 'submit'];
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Print selected file
	 *
	 * @param   string      $file       file
	 * @param   string      $module     module
	 * @param   string      $subdir     subdirectory of document like for expedition subdir is sendings
	 *
	 * @return  int                     0 if OK, >0 if KO
	 */
	public function printFile($file, $module, $subdir = '')
	{
		// phpcs:enable
		global $conf, $user, $langs;
		$error = 0;

		// select printer uri for module order, propal,...
		$sql = "SELECT rowid,printer_id,copy FROM " . MAIN_DB_PREFIX . "printing WHERE module = '" . $module . "' AND driver = 'printemail' AND userid = " . $user->id;
		$result = $this->db->query($sql);
		if ($result) {
			$obj = $this->db->fetch_object($result);
			if ($obj) {
				dol_syslog("Found a default printer for user " . $user->id . " = " . $obj->printer_id);
				$sendto = $obj->printer_id;
			} else {
				if (!empty($conf->global->PRINTEMAIL_URI_DEFAULT)) {
					dol_syslog("Will use default printer conf->global->PRINTEMAIL_URI_DEFAULT = " . $conf->global->PRINTEMAIL_URI_DEFAULT);
					$sendto = $conf->global->PRINTEMAIL_URI_DEFAULT;
				} else {
					$this->errors[] = 'NoDefaultPrinterDefined';
					$error++;
					return $error;
				}
			}
		} else {
			dol_print_error($this->db);
		}

		$fileprint = $conf->{$module}->dir_output;
		if ($subdir != '') {
			$fileprint .= '/' . $subdir;
		}
		$fileprint .= '/' . $file;

		// Send mail
		require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
		$subject = 'Envoi Impression ' . $file;
		$from = $conf->global->MAIN_MAIL_EMAIL_FROM;
		$message = 'ci joint fichier pour impression';

		$filepath[0] = $fileprint;
		$mimetype[0] = 'application/pdf';
		$filename[0] = $file;

		$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid);

		if ($mailfile->error) {
			$this->errors[] = $mailfile->error;
		} else {
			$result = $mailfile->sendfile();
			if ($result) {
				$error = 0;
				$this->errors[] = $langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($sendto, 2));
			}
		}

		if ($error == 0) {
			$this->errors[] = 'PRINTEMAIL: Job added';
		}

		return $error;
	}

	/**
	 *  Return list of available printers
	 *
	 *  @return  int                     0 if OK, >0 if KO
	 */
	public function listAvailablePrinters()
	{
		global $conf, $langs;
		$error = 0;

		$html = '<tr class="liste_titre">';
		$html .= '<td>' . $langs->trans('Email_Uri') . '</td>';
		$html .= '<td>' . $langs->trans('Printer_Name') . '</td>';
		$html .= '<td align="center">' . $langs->trans("Select") . '</td>';
		$html .= "</tr>\n";
		$html .= '<tr class="oddeven">';
		$html .= '<td>' . $this->email . '</td>';
		$html .= '<td>' . $this->printername . '</td>';
		// Defaut
		$html .= '<td align="center">';
		if (getDolGlobalString('PRINTEMAIL_URI_DEFAULT') == $this->email) {
			$html .= img_picto($langs->trans("Default"), 'on');
		} else {
			$html .= '<a href="' . $_SERVER["PHP_SELF"] . '?action=setvalue&mode=test&varname=PRINTEMAIL_URI_DEFAULT&driver=printemail&token=' . newToken() . '&value=' . urlencode($this->email) . '" alt="' . $langs->trans("Default") . '">' . img_picto($langs->trans("Disabled"), 'off') . '</a>';
		}
		$html .= '</td>';
		$html .= "</tr>\n";
		$this->resprint = $html;

		return $error;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return list of available printers
	 *
	 *  @return array                list of printers
	 */
	public function getlistAvailablePrinters()
	{
		// phpcs:enable
		if (empty($this->email)) {
			// We dont have printers so return blank array
			$ret =  [];
		} else {
			// We have printers so returns printers as array
			$ret[] = $this->email;
		}

		return $ret;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  List jobs print
	 *
	 *  @param   string      $module     module
	 *
	 *  @return  int                     0 if OK, >0 if KO
	 */
	public function listJobs($module)
	{
		// phpcs:enable
		global $conf, $bc;
		$error = 0;
		$html = '';
		$html .= '<table width="100%" class="noborder">';
		$html .= '<tr class="liste_titre">';
		$html .= '<td>Id</td>';
		$html .= '<td>Owner</td>';
		$html .= '<td>Printer</td>';
		$html .= '<td>File</td>';
		$html .= '<td>Status</td>';
		$html .= '<td>Cancel</td>';
		$html .= '</tr>' . "\n";
		//$jobs = $ipp->jobs_attributes;
		//$html .= '<pre>'.print_r($jobs,true).'</pre>';
		//foreach ($jobs as $value )
		//{
		//    $html .= '<tr class="oddeven">';
		//    $html .= '<td>'.$value->job_id->_value0.'</td>';
		//    $html .= '<td>'.$value->job_originating_user_name->_value0.'</td>';
		//    $html .= '<td>'.$value->printer_uri->_value0.'</td>';
		//    $html .= '<td>'.$value->job_name->_value0.'</td>';
		//    $html .= '<td>'.$value->job_state->_value0.'</td>';
		//    $html .= '<td>'.$value->job_uri->_value0.'</td>';
		//    $html .= '</tr>';
		//}
		$html .= "</table>";
		print $html;
	}
}
