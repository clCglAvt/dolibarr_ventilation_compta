<?php
/* Copyright (C) 2004-2009 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2006-2007 Yannick Warnier		<ywarnier@beeznest.org>
 * Copyright (C) 2011	   Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2012-2016 Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012      Cédric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014 Raphaël Doursenaud   <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
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
 *      \file       htdocs/core/lib/tax.lib.php
 *      \ingroup    tax
 *      \brief      Library for tax module
 */


/**
 * Prepare array with list of tabs
 *
 * @param   ChargeSociales	$object		Object related to tabs
 * @return  array				Array of tabs to show
 */
function tax_prepare_head(ChargeSociales $object)
{
    global $db, $langs, $conf, $user;

    $h = 0;
    $head = array();

	$head[$h][0] = DOL_URL_ROOT.'/compta/sociales/charges.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Card');
	$head[$h][2] = 'card';
	$h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'tax');

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->tax->dir_output . "/" . dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir,'files',0,'','(\.meta|_preview\.png)$'));
    $nbLinks=Link::count($db, $object->element, $object->id);
	$head[$h][0] = DOL_URL_ROOT.'/compta/sociales/document.php?id='.$object->id;
	$head[$h][1] = $langs->trans("Documents");
	if (($nbFiles+$nbLinks) > 0) $head[$h][1].= ' <span class="badge">'.($nbFiles+$nbLinks).'</span>';
	$head[$h][2] = 'documents';
	$h++;

    $head[$h][0] = DOL_URL_ROOT.'/compta/sociales/info.php?id='.$object->id;
    $head[$h][1] = $langs->trans("Info");
    $head[$h][2] = 'info';
    $h++;

    complete_head_from_modules($conf,$langs,$object,$head,$h,'tax','remove');

    return $head;
}


/**
 *  Look for collectable VAT clients in the chosen year (and month)
 *
 *  @param	DoliDB	$db          	Database handle
 *  @param  int		$y           	Year
 *  @param  string	$date_start  	Start date
 *  @param  string	$date_end    	End date
 *  @param  int		$modetax     	0 or 1 (option vat on debit, 1 => $modecompta = 'CREANCES-DETTES')
 *  @param  string	$direction   	'sell' or 'buy'
 *  @param  int		$m				Month
 *  @return array       			List of customers third parties with vat, -1 if no accountancy module, -2 if not yet developped, -3 if error
 */
function vat_by_thirdparty($db, $y, $date_start, $date_end, $modetax, $direction, $m=0)
{
    global $conf;

    $list=array();
	
    if ($direction == 'sell')
    {
        $invoicetable='facture';
        $invoicedettable='facturedet';
        $fk_facture='fk_facture';
        $fk_facture2='fk_facture';
        $fk_payment='fk_paiement';
        $total_tva='total_tva';
        $ftotal_tva='tva';
        $paymenttable='paiement';
        $paymentfacturetable='paiement_facture';
        $invoicefieldref='facnumber';
		$total_ht = 'total';
    }
    if ($direction == 'buy')
    {
        $invoicetable='facture_fourn';
        $invoicedettable='facture_fourn_det';
        $fk_facture='fk_facture_fourn';
        $fk_facture2='fk_facturefourn';
        $fk_payment='fk_paiementfourn';
        $total_tva='tva';
        $ftotal_tva='total_tva';
        $paymenttable='paiementfourn';
        $paymentfacturetable='paiementfourn_facturefourn';
        $invoicefieldref='ref';
		$total_ht = 'total_ht';
    }


    // Define sql request
    $sql='';
    if ($modetax == 1)
    {
        // If vat paid on due invoices (non draft)
        $sql = "SELECT s.rowid as socid, s.nom as name, s.tva_intra as tva_intra, s.tva_assuj as assuj,";
        $sql.= " sum(f.$total_ht) as amount, sum(f.".$total_tva.") as tva,";
        $sql.= " sum(f.localtax1) as localtax1,";
        $sql.= " sum(f.localtax2) as localtax2";
        $sql.= " FROM ".MAIN_DB_PREFIX.$invoicetable." as f,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE f.entity = " . $conf->entity;
        $sql.= " AND f.fk_statut in (1,2)"; // Validated or paid (partially or completely)
        if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
        else $sql.= " AND f.type IN (0,1,2,3,5)";
        if ($y && $m)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
        }
        else if ($y)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
        }
        if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
        $sql.= " AND s.rowid = f.fk_soc";
        $sql.= " GROUP BY s.rowid, s.nom, s.tva_intra, s.tva_assuj";
    }
    else
    {
print 'Non traité dans cette version';
return;
// MODIF CCA - TVA à l-encaissement sur produit
		// SI TAX_CASHING_PROD_PAYMENT = PRODPAYMENT
		//	Service sur paiement
		//	Produit sur paiement
		// else TAX_CASHING_PROD_PAYMENT = PRODDEBIT
		//	Service sur paiement
		//	Produit sur facture
		
        // Tva sur factures payes (should be on payment)
		
	
        // Tva sur factures payes (should be on payment)
/*      $sql = "SELECT s.rowid as socid, s.nom as nom, s.tva_intra as tva_intra, s.tva_assuj as assuj,";
        $sql.= " sum(fd.total_ht) as amount, sum(".$total_tva.") as tva";
        $sql.= " FROM ".MAIN_DB_PREFIX.$invoicetable." as f, ".MAIN_DB_PREFIX.$invoicetable." as fd, ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE ";
        $sql.= " f.fk_statut in (2)";   // Paid (partially or completely)
        if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
		else $sql.= " AND f.type IN (0,1,2,3,5)";
        if ($y && $m)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
        }
        else if ($y)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
        }
        if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
        $sql.= " AND s.rowid = f.fk_soc AND f.rowid = fd.".$fk_facture;
        $sql.= " GROUP BY s.rowid as socid, s.nom as nom, s.tva_intra as tva_intra, s.tva_assuj as assuj";
*/
	}
//Fin Modif CCA 
    if (! $sql) return -1;

    dol_syslog("Tax.lib:thirdparty", LOG_DEBUG);
    $resql = $db->query($sql);
    if ($resql)
    {
        while($assoc = $db->fetch_object($resql))
        {
            $list[] = $assoc;
        }
        $db->free($resql);
        return $list;
    }
    else
    {
        dol_print_error($db);
        return -3;
    }
}

/**
 *  Gets VAT to collect for the given year (and given quarter or month)
 *  The function gets the VAT in split results, as the VAT declaration asks
 *  to report the amounts for different VAT rates as different lines.
 *  This function also accounts recurrent invoices.
 *
 *  @param	DoliDB	$db          	Database handler object
 *  @param  int		$y           	Year
 *  @param  int		$q           	Quarter
 *  @param  string	$date_start  	Start date
 *  @param  string	$date_end    	End date
 *  @param  int		$modetax     	0 or 1 (option vat on debit)
 *  @param  int		$direction   	'sell' (customer invoice) or 'buy' (supplier invoices)
 *  @param  int		$m           	Month
 *  @return array       			List of quarters with vat
 */
function vat_by_date($db, $y, $q, $date_start, $date_end, $modetax, $direction, $m=0)
{
    global $conf;

    $list=array();

    if ($direction == 'sell')
    {
        $invoicetable='facture';
        $invoicedettable='facturedet';
        $fk_facture='fk_facture';
        $fk_facture2='fk_facture';
        $fk_payment='fk_paiement';
        $total_tva='total_tva';
        $total_localtax1='total_localtax1';
        $total_localtax2='total_localtax2';
        $paymenttable='paiement';
        $paymentfacturetable='paiement_facture';
        $invoicefieldref='facnumber';
    }
    if ($direction == 'buy')
    {
        $invoicetable='facture_fourn';
        $invoicedettable='facture_fourn_det';
        $fk_facture='fk_facture_fourn';
        $fk_facture2='fk_facturefourn';
        $fk_payment='fk_paiementfourn';
        $total_tva='tva';
        $total_localtax1='total_localtax1';
        $total_localtax2='total_localtax2';
        $paymenttable='paiementfourn';
        $paymentfacturetable='paiementfourn_facturefourn';
        $invoicefieldref='ref';
    }

    // CAS DES BIENS
    // Define sql request
    $sql='';
    if ($modetax == 1)  // Option vat on delivery for goods (payment) and debit invoice for services
    {
        // Count on delivery date (use invoice date as delivery is unknown)
        $sql = "SELECT d.rowid, d.product_type as dtype, d.".$fk_facture." as facid, d.tva_tx as rate, d.total_ht as total_ht, d.total_ttc as total_ttc, d.".$total_tva." as total_vat, d.description as descr,";
        $sql .=" d.".$total_localtax1." as total_localtax1, d.".$total_localtax2." as total_localtax2, ";
        $sql.= " d.date_start as date_start, d.date_end as date_end,";
        $sql.= " f.".$invoicefieldref." as facnum, f.type, f.total_ttc as ftotal_ttc, f.datef, s.nom as company_name, s.rowid as company_id,";
        $sql.= " p.rowid as pid, p.ref as pref, p.fk_product_type as ptype,";
        $sql.= " 0 as payment_id, 0 as payment_amount";
        $sql.= " FROM ".MAIN_DB_PREFIX.$invoicetable." as f,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s,";
        $sql.= " ".MAIN_DB_PREFIX.$invoicedettable." as d " ;
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p on d.fk_product = p.rowid";
        $sql.= " WHERE f.entity = " . $conf->entity;
        $sql.= " AND f.fk_statut in (1,2)"; // Validated or paid (partially or completely)
        if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
        else $sql.= " AND f.type IN (0,1,2,3,5)";
        $sql.= " AND f.rowid = d.".$fk_facture;
        $sql.= " AND s.rowid = f.fk_soc";
        if ($y && $m)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
        }
        else if ($y)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
        }
        if ($q) $sql.= " AND (date_format(f.datef,'%m') > ".(($q-1)*3)." AND date_format(f.datef,'%m') <= ".($q*3).")";
        if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
        $sql.= " AND (d.product_type = 0";                              // Limit to products
        $sql.= " AND d.date_start is null AND d.date_end IS NULL)";     // enhance detection of service
        $sql.= " ORDER BY d.rowid, d.".$fk_facture;
    }
    else    // Option vat on delivery for goods (payments) and payments for services
    {
		
// MODIF CCA - TVA à l'encaissement sur produit
		// cas où TAX_CASHING_PROD_PAYMENT  = 	'PRODDEBIT', c'est à dire le standard français
        // Count on delivery date (use invoice date as delivery is unknown)
       if ($conf->global->TAX_CASHING_PROD_PAYMENT == 'PRODDEBIT') {
			$sql = "SELECT d.rowid, d.product_type as dtype, d.".$fk_facture." as facid, d.tva_tx as rate, d.total_ht as total_ht, d.total_ttc as total_ttc, d.".$total_tva." as total_vat, d.description as descr,";
			$sql .=" d.".$total_localtax1." as total_localtax1, d.".$total_localtax2." as total_localtax2, ";
			$sql.= " d.date_start as date_start, d.date_end as date_end,";
			$sql.= " f.".$invoicefieldref." as facnum, f.type, f.total_ttc as ftotal_ttc, f.datef as date_f, s.nom as company_name, s.rowid as company_id,";
			$sql.= " p.rowid as pid, p.ref as pref, p.fk_product_type as ptype,";
			$sql.= " 0 as payment_id, 0 as payment_amount";
			$sql.= " FROM ".MAIN_DB_PREFIX.$invoicetable." as f,";
			$sql.= " ".MAIN_DB_PREFIX."societe as s,";
			$sql.= " ".MAIN_DB_PREFIX.$invoicedettable." as d " ;
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p on d.fk_product = p.rowid";
			$sql.= " WHERE f.entity = " . $conf->entity;
			$sql.= " AND f.fk_statut in (1,2)"; // Validated or paid (partially or completely)
			if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
			else $sql.= " AND f.type IN (0,1,2,3,5)";
			$sql.= " AND f.rowid = d.".$fk_facture;
			$sql.= " AND s.rowid = f.fk_soc";
			if ($y && $m)
			{
				$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
				$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
			}
			else if ($y)
			{
				$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
				$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
			}
			if ($q) $sql.= " AND (date_format(f.datef,'%m') > ".(($q-1)*3)." AND date_format(f.datef,'%m') <= ".($q*3).")";
			if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
			$sql.= " AND (d.product_type = 0";                              // Limit to products
			$sql.= " AND d.date_start is null AND d.date_end IS NULL)";     // enhance detection of service
			$sql.= " ORDER BY d.rowid, d.".$fk_facture;
			//print $sql;
	   }

	   if ($conf->global->TAX_CASHING_PROD_PAYMENT == 'PRODPAYMENT') {
		   // la TVA sur les produits exigible au paiement
	$sql = "SELECT  det_id as rowid,  dtype, facid, rate, descr, ";
	$sql .= "det_ht*sum(payment_amount)/ftotal_ttc as total_ht, ";
	$sql .= "det_ttc*sum(payment_amount)/ftotal_ttc as total_ttc, ";
	$sql .= "det_tva*sum(payment_amount)/ftotal_ttc as total_vat, ";
	$sql .= "det_localtax1*sum(payment_amount)/ftotal_ttc as total_localtax1, ";
	$sql .= " det_localtax2*sum(payment_amount)/ftotal_ttc as total_localtax2, ";
	$sql .= " date_start,  date_end, facnum, ftype as type, ";
	$sql .= "ftotal_ttc, 	  date_f,  company_name,  company_id,  pid ";
	$sql .= "FROM  		(select  ";
	$sql .= "	case when not isnull(pa.rowid) then  pf.amount " ;
	$sql .= "		else case when not isnull(paav.rowid) then fav.total_ttc  ";
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell')  
			$sql .= "else case when not isnull(remacp.rowid) then facp.total_ttc end  ";
	$sql .= "	end end as payment_amount,  ";
	$sql .= "	f.total_ttc as ftotal_ttc,  ";
	$sql .= "	 d.total_ht as det_ht, d.total_ttc as det_ttc, d.".$total_tva." as  det_tva,  d.rowid as det_id, d.product_type as dtype, d.".$fk_facture." as facid, d.tva_tx as rate , d.description as descr,  ";
	$sql .= "	 d.".$total_localtax1." as det_localtax1, d.".$total_localtax2." as det_localtax2, d.date_start as date_start, d.date_end as date_end,  f.".$invoicefieldref." as facnum, f.type as ftype,  ";
	$sql .= "	 f.datef as date_f,  s.nom as company_name, s.rowid as company_id, p.rowid as pid, p.ref as pref, p.fk_product_type as ptype, 0 as payment_id ";
	$sql .= "	FROM ".MAIN_DB_PREFIX.$invoicetable." as f  ";
	$sql .= "	/* facture paye dans la periode */";
	$sql .= "	left join  ".MAIN_DB_PREFIX.$paymentfacturetable." as pf on f.rowid = pf.".$fk_facture2  ;
	$sql .= "	left join  ".MAIN_DB_PREFIX.$paymenttable." as pa  on pa.rowid = pf.".$fk_payment  ;
	if ($y &&$m)		{
		$sql.= " AND pa.datep >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
		$sql.= " AND pa.datep <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
	}
	else if ($y)	{
		$sql.= " AND pa.datep >= '".$db->idate(dol_get_first_day($y,1,false))."'";
		$sql.= " AND pa.datep <= '".$db->idate(dol_get_last_day($y,12,false))."'";
	}
	if ($q) $sql.= " AND (date_format(pa.datep,'%m') > ".(($q-1)*3)." AND date_format(pa.datep,'%m') <= ".($q*3).")";
	if ($date_start && $date_end) $sql.= " AND pa.datep >= '".$db->idate($date_start)."' AND pa.datep <= '".$db->idate($date_end)."'";

		
	$sql .= "	/* avoir ayant un paiement dans la periode  */";
		$sql .= "	left join  ".MAIN_DB_PREFIX."societe_remise_except as remav on remav.fk_facture =  f.rowid   ";
		$sql .= "	left join ".MAIN_DB_PREFIX.$invoicetable." as fav on remav.fk_facture_source  = fav.rowid  and fav.type = 2   ";
	$sql .= "	left join ".MAIN_DB_PREFIX.$paymentfacturetable." as pfav on fav.rowid = pfav.".$fk_facture2  ;
	$sql .= "	left join  ".MAIN_DB_PREFIX.$paymenttable." as paav  on paav.rowid = pfav.".$fk_payment  ;
	if ($y &&$m)		{
		$sql.= " AND paav.datep >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
		$sql.= " AND paav.datep <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
	}
	else if ($y)	{
		$sql.= " AND paav.datep >= '".$db->idate(dol_get_first_day($y,1,false))."'";
		$sql.= " AND paav.datep <= '".$db->idate(dol_get_last_day($y,12,false))."'";
	}
	if ($q) $sql.= " AND (date_format(paav.datep,'%m') > ".(($q-1)*3)." AND date_format(paav.datep,'%m') <= ".($q*3).")";
	if ($date_start && $date_end) $sql.= " AND paav.datep >= '".$db->idate($date_start)."' AND paav.datep <= '".$db->idate($date_end)."'";	
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell') {	
		$sql .= "	/* acompte d'une facture de la periode seulement pour les clients  */";
		$sql .= "	left join  ".MAIN_DB_PREFIX."societe_remise_except as remacp on remacp.fk_facture =  f.rowid   ";
		if ($y &&$m)		{
			$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
			$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
		}
		else if ($y)	{
			$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
			$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
		}
		if ($q) $sql.= " AND (date_format(f.datef,'%m') > ".(($q-1)*3)." AND date_format(f.datef,'%m') <= ".($q*3).")";
		if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";	

		$sql .= "	left join ".MAIN_DB_PREFIX.$invoicetable." as facp on remacp.fk_facture_source = facp.rowid and facp.type = 3 ";
	}
	$sql .= " LEFT JOIN  ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc  ";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$invoicedettable." as d ON f.rowid = d.".$fk_facture ;
	$sql .= " LEFT JOIN  ".MAIN_DB_PREFIX."product as p on d.fk_product = p.rowid  ";
	$sql .= "where  ";
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell')		{
	$sql .= "	/* facture paye dans la periode */";
	$sql .= "((not isnull(pa.rowid) and isnull(paav.rowid) and isnull(remacp.rowid)) ";
			
	$sql .= "	/* avoir ayant un paiement dans la periode  */";
	$sql .= "	or 	( isnull(pa.rowid) and  not isnull(paav.rowid) and isnull(remacp.rowid)) ";
	$sql .= "	/* acompte d'une facture de la periode */";
	$sql .= "		or 		( isnull(pa.rowid) and isnull(paav.rowid) and  not isnull(remacp.rowid)))";
	$sql .= "	 and ";
	}
	elseif ((! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'buy') or empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS ))		{
	$sql .= "	/* facture paye dans la periode */";
	$sql .= "((not isnull(pa.rowid) and isnull(paav.rowid) ) ";			
	$sql .= "	/* avoir ayant un paiement dans la periode  */";
	$sql .= "	or 	( isnull(pa.rowid) and  not isnull(paav.rowid) )) ";
	
	$sql .= "	 and ";
	}
	$sql .= "  f.entity = " . $conf->entity;
	$sql .= " AND f.fk_statut in (1,2)   ";
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
	else $sql.= " AND f.type IN (0,1,2,3,5)";
	$sql .= "and (d.product_type = 0 AND d.date_start is  null AND  d.date_end IS  NULL) ";
	$sql .= ") as Tab ";
	$sql .= "group by det_id,  dtype, facid, rate, descr, 	 date_start,  date_end, facnum, ftype ,	  ftotal_ttc,	  date_f,  company_name,  company_id,  pid,  pref,  ptype,  payment_id, payment_amount  ";
	
    $sql.= " ORDER BY det_id, facid; ";
		//print '<br>'.$sql;
	   }
    }
	//if ($direction == 'buy' and  ($m == 5 ))  print '<br>'.$sql;
// Fin MODIF CCA 
    if (! $sql) return -1;
    if ($sql == 'TODO') return -2;
    if ($sql != 'TODO')
    {
        dol_syslog("Tax.lib.php::vat_by_date", LOG_DEBUG);

        $resql = $db->query($sql);
        if ($resql)
        {
            $rate = -1;
            $oldrowid='';
            while($assoc = $db->fetch_array($resql))
            {
                if (! isset($list[$assoc['rate']]['totalht']))  $list[$assoc['rate']]['totalht']=0;
                if (! isset($list[$assoc['rate']]['vat']))      $list[$assoc['rate']]['vat']=0;
                if (! isset($list[$assoc['rate']]['localtax1']))      $list[$assoc['rate']]['localtax1']=0;
                if (! isset($list[$assoc['rate']]['localtax2']))      $list[$assoc['rate']]['localtax2']=0;

                if ($assoc['rowid'] != $oldrowid)       // Si rupture sur d.rowid
                {
                    $oldrowid=$assoc['rowid'];
                    $list[$assoc['rate']]['totalht']  += $assoc['total_ht'];
                    $list[$assoc['rate']]['vat']      += $assoc['total_vat'];
                    $list[$assoc['rate']]['localtax1']      += $assoc['total_localtax1'];
                    $list[$assoc['rate']]['localtax2']      += $assoc['total_localtax2'];
                }
                $list[$assoc['rate']]['dtotal_ttc'][] = $assoc['total_ttc'];
                $list[$assoc['rate']]['dtype'][] = $assoc['dtype'];
                $list[$assoc['rate']]['datef'][] = $assoc['datef'];
                $list[$assoc['rate']]['company_name'][] = $assoc['company_name'];
                $list[$assoc['rate']]['company_id'][] = $assoc['company_id'];
                $list[$assoc['rate']]['ddate_start'][] = $db->jdate($assoc['date_start']);
                $list[$assoc['rate']]['ddate_end'][] = $db->jdate($assoc['date_end']);

                $list[$assoc['rate']]['facid'][] = $assoc['facid'];
                $list[$assoc['rate']]['facnum'][] = $assoc['facnum'];
                $list[$assoc['rate']]['type'][] = $assoc['type'];
                $list[$assoc['rate']]['ftotal_ttc'][] = $assoc['ftotal_ttc'];
                $list[$assoc['rate']]['descr'][] = $assoc['descr'];

                $list[$assoc['rate']]['totalht_list'][] = $assoc['total_ht'];
                $list[$assoc['rate']]['vat_list'][] = $assoc['total_vat'];
                $list[$assoc['rate']]['localtax1_list'][] = $assoc['total_localtax1'];
                $list[$assoc['rate']]['localtax2_list'][]  = $assoc['total_localtax2'];

                $list[$assoc['rate']]['pid'][] = $assoc['pid'];
                $list[$assoc['rate']]['pref'][] = $assoc['pref'];
                $list[$assoc['rate']]['ptype'][] = $assoc['ptype'];

                $list[$assoc['rate']]['payment_id'][] = $assoc['payment_id'];
                $list[$assoc['rate']]['payment_amount'][] = $assoc['payment_amount'];

                $rate = $assoc['rate'];
            }
        }
        else
        {
            dol_print_error($db);
            return -3;
        }
    }


    // CAS DES SERVICES

    // Define sql request
    $sql='';
    if ($modetax == 1)  // Option vat on delivery for goods (payment) and debit invoice for services
    {
        // Count on invoice date
        $sql = "SELECT d.rowid, d.product_type as dtype, d.".$fk_facture." as facid, d.tva_tx as rate, d.total_ht as total_ht, d.total_ttc as total_ttc, d.".$total_tva." as total_vat, d.description as descr,";
        $sql .=" d.".$total_localtax1." as total_localtax1, d.".$total_localtax2." as total_localtax2, ";
        $sql.= " d.date_start as date_start, d.date_end as date_end,";
        $sql.= " f.".$invoicefieldref." as facnum, f.type, f.total_ttc as ftotal_ttc, f.datef, s.nom as company_name, s.rowid as company_id,";
        $sql.= " p.rowid as pid, p.ref as pref, p.fk_product_type as ptype,";
        $sql.= " 0 as payment_id, 0 as payment_amount";
        $sql.= " FROM ".MAIN_DB_PREFIX.$invoicetable." as f,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s,";
        $sql.= " ".MAIN_DB_PREFIX.$invoicedettable." as d " ;
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p on d.fk_product = p.rowid";
        $sql.= " WHERE f.entity = " . $conf->entity;
        $sql.= " AND f.fk_statut in (1,2)"; // Validated or paid (partially or completely)
        if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
		else $sql.= " AND f.type IN (0,1,2,3,5)";
        $sql.= " AND f.rowid = d.".$fk_facture;
        $sql.= " AND s.rowid = f.fk_soc";
        if ($y && $m)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
        }
        else if ($y)
        {
            $sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
            $sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
        }
        if ($q) $sql.= " AND (date_format(f.datef,'%m') > ".(($q-1)*3)." AND date_format(f.datef,'%m') <= ".($q*3).")";
        if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
        $sql.= " AND (d.product_type = 1";                              // Limit to services
        $sql.= " OR d.date_start is NOT null OR d.date_end IS NOT NULL)";       // enhance detection of service
        $sql.= " ORDER BY d.rowid, d.".$fk_facture; 
    }
    else    // Option vat on payments for services
    {		
		$sql = "SELECT  det_id as rowid ,  dtype, facid, rate, descr, ";
		$sql .= "det_ht*sum(payment_amount)/ftotal_ttc as total_ht, ";
		$sql .= "det_ttc*sum(payment_amount)/ftotal_ttc as total_ttc, ";
		$sql .= "det_tva*sum(payment_amount)/ftotal_ttc as total_vat, ";
		$sql .= "det_localtax1*sum(payment_amount)/ftotal_ttc as total_localtax1, ";
		$sql .= " det_localtax2*sum(payment_amount)/ftotal_ttc as total_localtax2, ";
		$sql .= " date_start,  date_end, facnum, ftype as type, ";
		$sql .= "ftotal_ttc, 	  date_f,  company_name,  company_id,  pid,  pref,  ptype,  payment_id, payment_amount  ";
		$sql .= "FROM  		(select  ";
		$sql .= "	case when not isnull(pa.rowid) then  pf.amount ";
		$sql .= "		else case when not isnull(paav.rowid) then fav.total_ttc  ";
		if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell')  
				$sql .= "else case when not isnull(remacp.rowid) then facp.total_ttc end  ";
		$sql .= "	end end as payment_amount,  ";
		$sql .= "	f.total_ttc as ftotal_ttc,  ";
		$sql .= "	 d.total_ht as det_ht, d.total_ttc as det_ttc, d.".$total_tva." as  det_tva,  d.rowid as det_id, d.product_type as dtype, d.".$fk_facture." as facid, d.tva_tx as rate , d.description as descr,  ";
		$sql .= "	 d.".$total_localtax1." as det_localtax1, d.".$total_localtax2." as det_localtax2, d.date_start as date_start, d.date_end as date_end,  f.".$invoicefieldref." as facnum, f.type as ftype,  ";
		$sql .= "	 f.datef as date_f,  s.nom as company_name, s.rowid as company_id, p.rowid as pid, p.ref as pref, p.fk_product_type as ptype, 0 as payment_id ";
		$sql .= "	FROM ".MAIN_DB_PREFIX.$invoicetable." as f  ";
		$sql .= "	/* facture paye dans la periode */" ;
		$sql .= "	left join ".MAIN_DB_PREFIX.$paymentfacturetable." as pf on f.rowid = pf.".$fk_facture2 ;
		$sql .= "	left join  ".MAIN_DB_PREFIX.$paymenttable." as pa  on pa.rowid = pf.".$fk_payment ;
		if ($y &&$m)		{
			$sql.= " AND pa.datep >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
			$sql.= " AND pa.datep <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
		}
		else if ($y)	{
			$sql.= " AND pa.datep >= '".$db->idate(dol_get_first_day($y,1,false))."'";
			$sql.= " AND pa.datep <= '".$db->idate(dol_get_last_day($y,12,false))."'";
		}
		if ($q) $sql.= " AND (date_format(pa.datep,'%m') > ".(($q-1)*3)." AND date_format(pa.datep,'%m') <= ".($q*3).")";
		if ($date_start && $date_end) $sql.= " AND pa.datep >= '".$db->idate($date_start)."' AND pa.datep <= '".$db->idate($date_end)."'";

			
		$sql .= "	/* avoir ayant un paiement dans la periode */" ;
		$sql .= "	left join  ".MAIN_DB_PREFIX."societe_remise_except as remav on remav.fk_facture =  f.rowid   ";
		$sql .= "	left join ".MAIN_DB_PREFIX.$invoicetable." as fav on remav.fk_facture_source  = fav.rowid  and fav.type = 2   ";
		$sql .= "	left join ".MAIN_DB_PREFIX.$paymentfacturetable." as pfav on fav.rowid = pfav.".$fk_facture2;
		$sql .= "	left join  ".MAIN_DB_PREFIX.$paymenttable." as paav  on paav.rowid = pfav.".$fk_payment ;
		if ($y &&$m)		{
			$sql.= " AND paav.datep >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
			$sql.= " AND paav.datep <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
		}
		else if ($y)	{
			$sql.= " AND paav.datep >= '".$db->idate(dol_get_first_day($y,1,false))."'";
			$sql.= " AND paav.datep <= '".$db->idate(dol_get_last_day($y,12,false))."'";
		}
		if ($q) $sql.= " AND (date_format(paav.datep,'%m') > ".(($q-1)*3)." AND date_format(paav.datep,'%m') <= ".($q*3).")";
		if ($date_start && $date_end) $sql.= " AND paav.datep >= '".$db->idate($date_start)."' AND paav.datep <= '".$db->idate($date_end)."'";	
		if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell') {	
			$sql .= "	/* acompte d'une facture de la periode seulement pour les clients */"  ;
			$sql .= "	left join  ".MAIN_DB_PREFIX."societe_remise_except as remacp on remacp.fk_facture =  f.rowid   ";
			if ($y &&$m)		{
				$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,$m,false))."'";
				$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,$m,false))."'";
			}
			else if ($y)	{
				$sql.= " AND f.datef >= '".$db->idate(dol_get_first_day($y,1,false))."'";
				$sql.= " AND f.datef <= '".$db->idate(dol_get_last_day($y,12,false))."'";
			}
			if ($q) $sql.= " AND (date_format(f.datef,'%m') > ".(($q-1)*3)." AND date_format(f.datef,'%m') <= ".($q*3).")";
			if ($date_start && $date_end) $sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";	

			$sql .= "	left join  ".MAIN_DB_PREFIX."facture as facp on remacp.fk_facture_source = facp.rowid and facp.type = 3 ";
		} 
		$sql .= " LEFT JOIN  ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc  ";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$invoicedettable." as d ON f.rowid = d.".$fk_facture ;
		$sql .= " LEFT JOIN  ".MAIN_DB_PREFIX."product as p on d.fk_product = p.rowid  ";
		$sql .= "where  ";
		if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'sell')		{
		$sql .= "	/* facture paye dans la periode */";
		$sql .= "((not isnull(pa.rowid) and isnull(paav.rowid) and isnull(remacp.rowid)) ";
				
		$sql .= "	/* avoir ayant un paiement dans la periode  */";
		$sql .= "	or 	( isnull(pa.rowid) and  not isnull(paav.rowid) and isnull(remacp.rowid)) ";
		$sql .= "	/* acompte d'une facture de la periode */";
		$sql .= "		or 		( isnull(pa.rowid) and isnull(paav.rowid) and  not isnull(remacp.rowid)))";
		$sql .= "	 and ";
		}
		elseif ((! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) and $direction == 'buy') or empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS ))		{
		$sql .= "	/* facture paye dans la periode */";
		$sql .= "(( pa.rowid is not null and paav.rowid is null ) ";
				
		$sql .= "	/* avoir ayant un paiement dans la periode  */";
		$sql .= "	or 	( pa.rowid is null and  paav.rowid is not null )) ";
		$sql .= "	 and ";
		}
			
		$sql .= "  f.entity = " . $conf->entity;
		$sql .= " AND f.fk_statut in (1,2)   ";
		if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2,5)";
		else $sql.= " AND f.type IN (0,1,2,3,5)";
			$sql.= " AND (d.product_type = 1";                              // Limit to services
			$sql.= " OR d.date_start is NOT null OR d.date_end IS NOT NULL)";       // enhance detection of service
		$sql .= ") as Tab ";
		$sql .= "group by det_id,  dtype, facid, rate, descr, 	 date_start,  date_end, facnum, ftype ,	  ftotal_ttc,	  date_f,  company_name,  company_id,  pid,  pref,  ptype,  payment_id, payment_amount  ";
		
		$sql.= " ORDER BY det_id, facid; ";		
    }
	//print $sql.'<br>';
	//if ($direction == 'buy' and ($m == 5 ))  print '<br>'.$sql;
    if (! $sql)
    {
        dol_syslog("Tax.lib.php::vat_by_date no accountancy module enabled".$sql,LOG_ERR);
        return -1;  // -1 = Not accountancy module enabled
    }
    if ($sql == 'TODO') return -2; // -2 = Feature not yet available
    if ($sql != 'TODO')
    {
        dol_syslog("Tax.lib.php::vat_by_date", LOG_DEBUG);
        $resql = $db->query($sql);
        if ($resql)
        {
            $rate = -1;
            $oldrowid='';
            while($assoc = $db->fetch_array($resql))
            {
                if (! isset($list[$assoc['rate']]['totalht']))  $list[$assoc['rate']]['totalht']=0;
                if (! isset($list[$assoc['rate']]['vat']))      $list[$assoc['rate']]['vat']=0;
				if (! isset($list[$assoc['rate']]['localtax1']))      $list[$assoc['rate']]['localtax1']=0;
                if (! isset($list[$assoc['rate']]['localtax2']))      $list[$assoc['rate']]['localtax2']=0;

                if ($assoc['rowid'] != $oldrowid)       // Si rupture sur d.rowid
                {
                    $oldrowid=$assoc['rowid'];
                    $list[$assoc['rate']]['totalht']  += $assoc['total_ht'];
                    $list[$assoc['rate']]['vat']      += $assoc['total_vat'];
                    $list[$assoc['rate']]['localtax1']	 += $assoc['total_localtax1'];
                    $list[$assoc['rate']]['localtax2']	 += $assoc['total_localtax2'];
                }
                $list[$assoc['rate']]['dtotal_ttc'][] = $assoc['total_ttc'];
                $list[$assoc['rate']]['dtype'][] = $assoc['dtype'];
                $list[$assoc['rate']]['datef'][] = $assoc['datef'];
                $list[$assoc['rate']]['company_name'][] = $assoc['company_name'];
                $list[$assoc['rate']]['company_id'][] = $assoc['company_id'];
                $list[$assoc['rate']]['ddate_start'][] = $db->jdate($assoc['date_start']);
                $list[$assoc['rate']]['ddate_end'][] = $db->jdate($assoc['date_end']);

                $list[$assoc['rate']]['facid'][] = $assoc['facid'];
                $list[$assoc['rate']]['facnum'][] = $assoc['facnum'];
                $list[$assoc['rate']]['type'][] = $assoc['type'];
                $list[$assoc['rate']]['ftotal_ttc'][] = $assoc['ftotal_ttc'];
                $list[$assoc['rate']]['descr'][] = $assoc['descr'];

                $list[$assoc['rate']]['totalht_list'][] = $assoc['total_ht'];
                $list[$assoc['rate']]['vat_list'][] = $assoc['total_vat'];
                $list[$assoc['rate']]['localtax1_list'][] = $assoc['total_localtax1'];
                $list[$assoc['rate']]['localtax2_list'][] = $assoc['total_localtax2'];

                $list[$assoc['rate']]['pid'][] = $assoc['pid'];
                $list[$assoc['rate']]['pref'][] = $assoc['pref'];
                $list[$assoc['rate']]['ptype'][] = $assoc['ptype'];

                $list[$assoc['rate']]['payment_id'][] = $assoc['payment_id'];
                $list[$assoc['rate']]['payment_amount'][] = $assoc['payment_amount'];

                $rate = $assoc['rate'];
            }
        }
        else
        {
            dol_print_error($db);
            return -3;
        }
    }

    return $list;
}

