<?php
/*
 $Id: dom_edit.php,v 1.8 2006/02/17 18:20:08 olivier Exp $
 ----------------------------------------------------------------------
 AlternC - Web Hosting System
 Copyright (C) 2002 by the AlternC Development Team.
 http://alternc.org/
 ----------------------------------------------------------------------
 Based on:
 Valentin Lacambre's web hosting softwares: http://altern.org/
 ----------------------------------------------------------------------
 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html
 ----------------------------------------------------------------------
 Original Author of file: Benjamin Sonntag
 Purpose of file: Edit a domain parameters
 ----------------------------------------------------------------------
*/
require_once("../class/config.php");
include_once("head.php");

$fields = array (
	"domain"    => array ("request", "string", ""),
	"sub"       => array ("request", "string", ""),
);
getFields($fields);

$dom->lock();
if (!$r=$dom->get_domain_all($domain)) {
	$error=$err->errstr();
}
$dom->unlock();

?>
<script type="text/javascript">
function dnson() {
	// Active les composants DNS :
	if (document.forms["fdns"].mx.disabled!=null)
		document.forms["fdns"].mx.disabled=false;
	if (document.forms["fdns"].emailon.disabled!=null)
		document.forms["fdns"].emailon.disabled=true;
	if (document.forms["fdns"].emailoff.disabled!=null)
		document.forms["fdns"].emailoff.disabled=true;
}
function dnsoff() {
	// Active les composants DNS :
	if (document.forms["fdns"].mx.disabled!=null)
		document.forms["fdns"].mx.disabled=true;
	if (document.forms["fdns"].emailon.disabled!=null)
		document.forms["fdns"].emailon.disabled=false;
	if (document.forms["fdns"].emailoff.disabled!=null)
		document.forms["fdns"].emailoff.disabled=false;
}
</script>
<h3><?php printf(_("Editing subdomains of %s"),$domain); ?></h3>
<?php
	if (isset($error) && $error) {
		echo "<p class=\"error\">$error</p>";
	}
?>
<hr id="topbar"/>
<?php

if ($r['dns_action']=='UPDATE') {?>
  <p class="error"><?php __("This domain have some DNS change pending. Please wait."); ?></p>
<?php
} elseif ($r['dns_action']=='DELETE') {?>
  <p class="error"><?php __("You requested deletion of this domain.");?></p>
<?php
/*
  // Link hidden as long as the del_domain_cancel function is not complete
  <a href="dom_dodel.php?domain=<?php echo urlencode($domain);?>&del_cancel=true"><?php __("Clic here to cancel deletion");?></a>
*/
?>
  <?php
  include_once("foot.php");
  die();
}

?>
<br />
<!-- *****************************************
		 gestion des sous-domaines
 -->
<table class="tlist">
<tr><th colspan="2"> </th><th><?php __("Subdomain"); ?></th><th><?php __("Type");?></th><th><?php __("Status")?></th></tr>
<?php
$col=1;
$dt=$dom->domains_type_lst();
for($i=0;$i<$r["nsub"];$i++) {
	$col=3-$col;

?>
	<tr class="lst<?php echo $col; ?>">
    <?php if ( $r['sub'][$i]['web_action'] =='DELETE') { echo "<td colspan=2 />"; } else { ?>
		<td class="center">
    <?php  if (!(!$isinvited && $dt[strtolower($r["sub"][$i]["type"])]["enable"] != "ALL" )) { ?>
			<div class="ina"><a href="dom_subedit.php?domain=<?php echo urlencode($r["name"]) ?>&amp;sub=<?php  echo urlencode($r["sub"][$i]["name"]) ?>&amp;type=<?php  echo urlencode($r["sub"][$i]["type"]) ?>&amp;value=<?php echo urlencode($r["sub"][$i]['dest'])?>"><img src="images/edit.png" alt="<?php __("Edit"); ?>" /><?php __("Edit"); ?></a></div>
  <?php } ?>


			</td><td class="center">
    <?php  if (!(!$isinvited && $dt[strtolower($r["sub"][$i]["type"])]["enable"] != "ALL" )) { ?>
			<div class="ina"><a href="dom_subdel.php?domain=<?php echo urlencode($r["name"]) ?>&amp;sub=<?php  echo urlencode($r["sub"][$i]["name"]) ?>&amp;type=<?php  echo urlencode($r["sub"][$i]["type"]) ?>&amp;value=<?php echo urlencode($r["sub"][$i]['dest'])?>"><img src="images/delete.png" alt="<?php __("Delete"); ?>" /><?php __("Delete"); ?></a></div>
<?php } ?>
		</td>
    <?php } // end IF ==DELETE ?>
		<td><a href="http://<?php ecif($r["sub"][$i]["name"],$r["sub"][$i]["name"]."."); echo $r["name"] ?>" target="_blank"><?php ecif($r["sub"][$i]["name"],$r["sub"][$i]["name"]."."); echo $r["name"] ?></a></td>
		<td><?php __($r['sub'][$i]['type_desc']); ?>
 <?php 
 if ($r["sub"][$i]['type'] === 'VHOST') {
  echo '<br /><a href="bro_main.php?R='.urlencode($r["sub"][$i]["dest"]).'">'.htmlspecialchars($r["sub"][$i]["dest"]).'</a>';
} else {
  if ($r["sub"][$i]['type']) echo "<br />".htmlspecialchars($r["sub"][$i]["dest"]);
}
?></td>
		<td><?php 
			if (!(!$isinvited && $dt[strtolower($r["sub"][$i]["type"])]["enable"] != "ALL" )) {
				if ( $r['sub'][$i]['web_action'] !='DELETE') { 
					switch ($r['sub'][$i]['enable']) {
						case 'ENABLED':
							__("Enabled");
							echo "<br/><a href='dom_substatus.php?domain=".urlencode($r["name"])."&amp;sub=".urlencode($r["sub"][$i]["name"])."&amp;type=".urlencode($r["sub"][$i]["type"])."&amp;value=".urlencode($r["sub"][$i]['dest'])."&status=disable'>";__("Disable");echo "</a>";
							break;
						case 'ENABLE':
							__("Activation pending");
							break;
						case 'DISABLED':
							__("Disabled");
							echo "<br/><a href='dom_substatus.php?domain=".urlencode($r["name"])."&amp;sub=".urlencode($r["sub"][$i]["name"])."&amp;type=".urlencode($r["sub"][$i]["type"])."&amp;value=".urlencode($r["sub"][$i]['dest'])."&status=enable'>";__("Enable");echo "</a>";
							break;
						case 'DISABLE':
							__("Desactivation pending");
							break;
					}
				}
      }?></td>
		<td><?php 
      switch ($r['sub'][$i]['web_action']) {
        case 'UPDATE':
          __("Update pending");
          break;
        case 'DELETE':
          __("Deletion pending");
          break;
        case 'OK':
        default:
          break;
      }?></td>
            
	</tr>
<?php } ?>
</table>
<br />
<hr/>
<?php
$isedit=false;
require_once('dom_edit.inc.php');
sub_domains_edit($domain);
?>
<br />
<?php $mem->show_help("edit_domain"); ?>
<p>&nbsp;</p>
<!-- *****************************************
		 modification des parametres dns
 -->
<?php
if (!$r['noerase']) {
?>

<hr />
<h3><?php __("DNS & Email parameters"); ?></h3>
<form action="dom_editdns.php?domain=<?php echo urlencode($r["name"]) ?>" method="post" id="fdns" name="fdns">

<table class="tlist">
<tr>
  <td><?php __("Manage the DNS on the server ?"); ?></td>
  <td> 
     <input type="radio" id="yesdns" class="inc" name="dns" value="1"<?php cbox($r["dns"]); ?> />&nbsp;<label for="yesdns"><?php __("Yes"); ?></label>
      </td><td><input type="radio" id="nodns" class="inc" name="dns" value="0"<?php cbox(!$r["dns"]); ?> />&nbsp;<label for="nodns"><?php __("No"); ?></label>
  </td>
</tr>
</table>

<table class="tlist">
<tr>
  <td>
    <?php __("Manage the Emails Addresses of this domain on the server?"); ?>
  </td>
  <td> 
     <input type="radio" id="yesemail" class="inc" name="email" value="1"<?php cbox($r["mail"]); ?> />&nbsp;<label for="yesemail"><?php __("Yes"); ?></label>
     </td><td><input type="radio" id="noemail" class="inc" name="email" value="0"<?php cbox(!$r["mail"]); ?> />&nbsp;<label for="noemail"><?php __("No"); ?></label>
  </td>
</table>
<div style="padding-left: 30px; padding-bottom: 20px" class="error">    <?php __("Warning: If you set this to 'no', all your email accounts and aliases on this domain will be immediately deleted."); ?></div>

<input type="submit" class="inb" name="submit" value="<?php __("Submit the changes"); ?>" />
	</form>

<p>&nbsp</p>
<hr />
<h3><?php __("Domain removal"); ?></h3>

<br />
<?php printf(_("help_domain_del %s"),$domain); ?><br />
<form action="dom_dodel.php?domain=<?php echo urlencode($domain) ?>" method="post">
<p>
<input type="submit" class="inb" name="detruire" value="<?php printf(_("Delete %s from this server"),$domain); ?>" />
</p>
</form>

<br />
<hr />
<?php } // noerase ?>
<script type="text/javascript">
document.forms['main'].sub.focus();
</script>
<?php include_once("foot.php"); ?>
