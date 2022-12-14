<?php
/*******************************************************************************
 *
 *  filename    : ISTAddressVerification.php
 *  website     : http://www.churchcrm.io
 *  description : USPS address verification
 *
 ******************************************************************************/

// This file verifies family address information using an on-line XML
// service provided by Intelligent Search Technology, Ltd.  Fees required.
// See https://www.intelligentsearch.com/Hosted/User/
// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\ISTAddressLookup;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

function XMLparseIST($xmlstr, $xmlfield)
{
    // Function to parse XML data from Intelligent Search Technolgy, Ltd.

    if (!(strpos($xmlstr, "<$xmlfield>") === false) ||
          strpos($xmlstr, "</$xmlfield>" === false)) {
        $startpos = strpos($xmlstr, "<$xmlfield>") + strlen("<$xmlfield>");
        $endpos = strpos($xmlstr, "</$xmlfield>");

        if ($endpos < $startpos) {
            return '';
        }

        return mb_substr($xmlstr, $startpos, $endpos - $startpos);
    }

    return '';
}

// If user is not admin, redirect to the menu.
if (!AuthenticationManager::GetCurrentUser()->isAdmin()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Set the page title and include HTML header
$sPageTitle = gettext('US Address Verification');
require 'Include/Header.php';

if (strlen(SystemConfig::getValue('sISTusername')) && strlen(SystemConfig::getValue('sISTpassword'))) {
    $myISTAddressLookup = new ISTAddressLookup();
    $myISTAddressLookup->getAccountInfo(SystemConfig::getValue('sISTusername'), SystemConfig::getValue('sISTpassword'));
    $myISTReturnCode = $myISTAddressLookup->GetReturnCode();
    $myISTSearchesLeft = $myISTAddressLookup->GetSearchesLeft();
} else {
    $myISTReturnCode = '9';
    $myISTSearchesLeft = 'Missing sISTusername or sISTpassword';
}

if ($myISTReturnCode == '4') {
    ?>
  <div class="row">
    <div class="col-lg-12 col-md-7 col-sm-3">
      <div class="card card-body">
        <div class="alert alert-danger alert-dismissible">
          <h4><i class="icon fa fa-ban"></i>The Intelligent Search Technology, Ltd. XML web service is temporarily unavailable.</h4>
  <?php echo 'getAccountInfo ReturnCode = '.$myISTReturnCode ?>
          Please try again in 30 minutes.
          You may follow the URL below to log in and manage your Intelligent Search ';
          Technology account settings.  This link may also provide information pertaining to ';
          this service disruption.<br><br>
          <a href="https://www.intelligentsearch.com/Hosted/User/">https://www.intelligentsearch.com/Hosted/User/</a>';
        </div>
      </div>
    </div>
  </div>
  <?php
} elseif ($myISTReturnCode != '0') {
        ?>
  <div class="row">
    <div class="col-lg-12 col-md-7 col-sm-3">
      <div class="card card-body">
        <div class="alert alert-danger alert-dismissible">
          <h4><i class="icon fa fa-ban"></i>The Intelligent Search Technology, Ltd. XML web service is temporarily unavailable.</h4>
          <p><?php echo 'getAccountInfo ReturnCode = '.$myISTReturnCode ?></p>
          <p><?= $myISTSearchesLeft ?></p>
          <p>Please verify that your Intelligent Search Technology, Ltd. username and password are correct</p>
          <p><i>Admin -> Edit General Settings -> sISTusername</i></p>
          <p><i>Admin -> Edit General Settings -> sISTpassword</i></p>
          <p>Follow the URL below to log in and manage your Intelligent Search Technology account settings.  If you do not already have an account you may establish an account at this URL. This software was written to work best with the service CorrectAddress(R) with Addons</p>
          <a href="https://www.intelligentsearch.com/Hosted/User/">https://www.intelligentsearch.com/Hosted/User/</a>
          <br><br>
          If you are sure that your account username and password are correct and that your
          account is in good standing it is possible that the server is currently unavailable
          but may be back online if you try again later.<br><br>
          ChurchCRM uses XML web services provided by Intelligent
          Search Technology, Ltd.  For information about CorrectAddress(R) Online Address
          Verification Service visit the following URL. This software was written to work
          best with the service CorrectAddress(R) with Addons. <br><br>
          <a href="http://www.intelligentsearch.com/address_verification/verify_address.html"> <?= gettext('http://www.intelligentsearch.com/address_verification/verify_address.html') ?></a>
        </div>
      </div>
    </div>
  </div>
  <?php
    } elseif ($myISTSearchesLeft == 'X') {
        echo "<br>\n";
        echo "Searches Left = $myISTSearchesLeft<br><br>\n";
        echo 'Follow the URL below to log in and manage your Intelligent Search Technology account ';
        echo "settings.<br>\n";

        echo '<a href="https://www.intelligentsearch.com/Hosted/User/">';
        echo 'https://www.intelligentsearch.com/Hosted/User/</a><br><br><br>\n';

        echo 'This software was written to work best with the service CorrectAddress(R) ';
        echo 'with Addons. <br><br><br>';
    } else {
        // IST account is valid and working.  Time to get to work.

        echo "<h3>\n";
        echo 'To conserve funds the following rules are used to determine if ';
        echo "an address lookup should be performed.<br>\n";
        echo "1) The family record has been added since the last lookup<br>\n";
        echo "2) The family record has been edited since the last lookup<br>\n";
        echo "3) It's been more than two years since the family record has been verified<br>\n";
        echo "4) The address must be a US address (Country = United States)<br><br>\n";
        echo "</h3>\n";

        // Housekeeping ... Delete families from the table istlookup_lu that
        // do not exist in the table family_fam.  This happens whenever
        // a family is deleted from family_fam.  (Or, more rarely, if a family
        // moves to another country)

        $sSQL = 'SELECT lu_fam_ID FROM istlookup_lu ';
        $rsIST = RunQuery($sSQL);
        $iOrphanCount = 0;
        while ($aRow = mysqli_fetch_array($rsIST)) {
            extract($aRow);
            // verify that this ID exists in family_fam with
            // fam_Country = 'United States'
            $sSQL = 'SELECT count(fam_ID) as idexists FROM family_fam ';
            $sSQL .= "WHERE fam_ID='$lu_fam_ID' ";
            $sSQL .= "AND fam_Country='United States'";
            $rsExists = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsExists));
            if ($idexists == '0') {
                $sSQL = "DELETE FROM istlookup_lu WHERE lu_fam_ID='$lu_fam_ID'";
                RunQuery($sSQL);
                $iOrphanCount++;
            }
        }
        echo "<h4>\n";
        if ($iOrphanCount) {
            echo $iOrphanCount." Orphaned IDs deleted.<br>\n";
        }

        // More Housekeeping ... Delete families from the table istlookup_lu that
        // have had their family_fam records edited since the last lookup
        //
        // Note: If the address matches the information from the previous
        // lookup the delete is not necessary.  Perform this check to determine
        // if a delete is really needed.  This avoids the problem of having to do
        // a lookup AFTER the address has been corrected.

        $sSQL = 'SELECT * FROM family_fam INNER JOIN istlookup_lu ';
        $sSQL .= 'ON family_fam.fam_ID = istlookup_lu.lu_fam_ID ';
        $sSQL .= 'WHERE fam_DateLastEdited > lu_LookupDateTime ';
        $sSQL .= 'AND fam_DateLastEdited IS NOT NULL';
        $rsUpdated = RunQuery($sSQL);
        $iUpdatedCount = 0;
        while ($aRow = mysqli_fetch_array($rsUpdated)) {
            extract($aRow);

            $sFamilyAddress = $fam_Address1.$fam_Address2.$fam_City.
            $fam_State.$fam_Zip;
            $sLookupAddress = $lu_DeliveryLine1.$lu_DeliveryLine2.$lu_City.
            $lu_State.$lu_ZipAddon;

            // compare addresses
            if (strtoupper($sFamilyAddress) != strtoupper($sLookupAddress)) {
                // only delete mismatches from lookup table
                $sSQL = "DELETE FROM istlookup_lu WHERE lu_fam_ID='$fam_ID'";
                RunQuery($sSQL);
                $iUpdatedCount++;
            }
        }
        if ($iUpdatedCount) {
            echo $iUpdatedCount." Updated IDs deleted.<br>\n";
        }

        // More Housekeeping ... Delete families from the table istlookup_lu that
        // have not had a lookup performed in more than one year.  Zip codes and street
        // names occasionally change so a verification every two years is a good idea.

        $twoYearsAgo = date('Y-m-d H:i:s', strtotime('-24 months'));

        $sSQL = 'SELECT lu_fam_ID FROM istlookup_lu ';
        $sSQL .= "WHERE '$twoYearsAgo' > lu_LookupDateTime";
        $rsResult = RunQuery($sSQL);
        $iOutdatedCount = 0;
        while ($aRow = mysqli_fetch_array($rsResult)) {
            extract($aRow);
            $sSQL = "DELETE FROM istlookup_lu WHERE lu_fam_ID='$lu_fam_ID'";
            RunQuery($sSQL);
            $iOutdatedCount++;
        }
        if ($iOutdatedCount) {
            echo $iOutdatedCount." Outdated IDs deleted.<br>\n";
        }

        // All housekeeping is finished !!!
        // Get count of non-US addresses
        $sSQL = 'SELECT count(fam_ID) AS nonustotal FROM family_fam ';
        $sSQL .= "WHERE fam_Country NOT IN ('United States')";
        $rsResult = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsResult));
        $iNonUSCount = intval($nonustotal);
        if ($iNonUSCount) {
            echo $iNonUSCount." Non US addresses in database will not be verified.<br>\n";
        }

        // Get count of US addresses
        $sSQL = 'SELECT count(fam_ID) AS ustotal FROM family_fam ';
        $sSQL .= "WHERE fam_Country IN ('United States')";
        $rsResult = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsResult));
        $iUSCount = intval($ustotal);
        if ($iUSCount) {
            echo $iUSCount." Total US addresses in database.<br>\n";
        }

        // Get count of US addresses that do not require a fresh lookup
        $sSQL = 'SELECT count(lu_fam_ID) AS usokay FROM istlookup_lu';
        $rsResult = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsResult));
        $iUSOkay = intval($usokay);
        if ($iUSOkay) {
            echo $iUSOkay." US addresses have had lookups performed.<br>\n";
        }

        // Get count of US addresses ready for lookup
        $sSQL = 'SELECT count(fam_ID) AS newcount FROM family_fam ';
        $sSQL .= "WHERE fam_Country IN ('United States') AND fam_ID NOT IN (";
        $sSQL .= 'SELECT lu_fam_ID from istlookup_lu)';
        $rs = RunQuery($sSQL);
        extract(mysqli_fetch_array($rs));
        $iEligible = intval($newcount);
        if ($iEligible) {
            echo $iEligible." US addresses are eligible for lookup.<br>\n";
        } else {
            echo "There are no US addresses eligible for lookup.<br>\n";
        }
        echo '</h4>';

        if ($_GET['DoLookup']) {
            $startTime = time();  // keep tabs on how long this runs to avoid server timeouts

            echo "Lookups in process, screen refresh scheduled every 20 seconds.<br>\n"; ?>
    <table><tr><td><form method="POST" action="USISTAddressVerification.php">
            <input type=submit class=btn name=StopLookup value="Stop Lookups">
          </form></td></tr></table>
    <?php
    // Get list of fam_ID that do not exist in table istlookup_lu
    $sSQL = 'SELECT fam_ID, fam_Address1, fam_Address2, fam_City, fam_State ';
            $sSQL .= 'FROM family_fam LEFT JOIN istlookup_lu ';
            $sSQL .= 'ON fam_id = lu_fam_id ';
            $sSQL .= 'WHERE lu_fam_id IS NULL ';
            $rsResult = RunQuery($sSQL);

            $bNormalFinish = true;
            while ($aRow = mysqli_fetch_array($rsResult)) {
                extract($aRow);
                if (strlen($fam_Address2)) {
                    $fam_Address1 = $fam_Address2;
                    $fam_Address2 = '';
                }
                echo "Sent: $fam_Address1 $fam_Address2 ";
                echo "$fam_City $fam_State";
                echo "<br>\n";
                $myISTAddressLookup = new ISTAddressLookup();
                $myISTAddressLookup->SetAddress($fam_Address1, $fam_Address2, $fam_City, $fam_State);

                $ret = $myISTAddressLookup->wsCorrectA(SystemConfig::getValue('sISTusername'), SystemConfig::getValue('sISTpassword'));

                $lu_fam_ID = MySQLquote(addslashes($fam_ID));
                $lu_LookupDateTime = MySQLquote(addslashes(date('Y-m-d H:i:s')));
                $lu_DeliveryLine1 = MySQLquote(addslashes($myISTAddressLookup->GetAddress1()));
                $lu_DeliveryLine2 = MySQLquote(addslashes($myISTAddressLookup->GetAddress2()));
                $lu_City = MySQLquote(addslashes($myISTAddressLookup->GetCity()));
                $lu_State = MySQLquote(addslashes($myISTAddressLookup->GetState()));
                $lu_ZipAddon = MySQLquote(addslashes($myISTAddressLookup->GetZip()));
                $lu_Zip = MySQLquote(addslashes($myISTAddressLookup->GetZip5()));
                $lu_Addon = MySQLquote(addslashes($myISTAddressLookup->GetZip4()));
                $lu_LOTNumber = MySQLquote(addslashes($myISTAddressLookup->GetLOTNumber()));
                $lu_DPCCheckdigit = MySQLquote(addslashes($myISTAddressLookup->GetDPCCheckdigit()));
                $lu_RecordType = MySQLquote(addslashes($myISTAddressLookup->GetRecordType()));
                $lu_LastLine = MySQLquote(addslashes($myISTAddressLookup->GetLastLine()));
                $lu_CarrierRoute = MySQLquote(addslashes($myISTAddressLookup->GetCarrierRoute()));
                $lu_ReturnCodes = MySQLquote(addslashes($myISTAddressLookup->GetReturnCodes()));
                $lu_ErrorCodes = MySQLquote(addslashes($myISTAddressLookup->GetErrorCodes()));
                $lu_ErrorDesc = MySQLquote(addslashes($myISTAddressLookup->GetErrorDesc()));

                //echo "<br>" . $lu_ErrorCodes;

                $iSearchesLeft = $myISTAddressLookup->GetSearchesLeft();
                if (!is_numeric($iSearchesLeft)) {
                    $iSearchesLeft = 0;
                } else {
                    $iSearchesLeft = intval($iSearchesLeft);
                }

                echo 'Received: '.$myISTAddressLookup->GetAddress1().' ';
                echo $myISTAddressLookup->GetAddress2().' ';
                echo $myISTAddressLookup->GetLastLine().' '.$iSearchesLeft;
                if ($lu_ErrorDesc != 'NULL') {
                    echo ' '.$myISTAddressLookup->GetErrorDesc();
                }
                echo '<br><br>';

                if ($lu_ErrorCodes != "'xx'") {
                    // Error code xx is one of the following
                    // 1) Connection failure 2) Invalid username or password 3) No searches left
                    //
                    // Insert data into istlookup_lu table
                    //
                    $sSQL = 'INSERT INTO istlookup_lu (';
                    $sSQL .= '  lu_fam_ID,  lu_LookupDateTime,  lu_DeliveryLine1, ';
                    $sSQL .= '  lu_DeliveryLine2,  lu_City,  lu_State,  lu_ZipAddon, ';
                    $sSQL .= '  lu_Zip,  lu_Addon,  lu_LOTNumber,  lu_DPCCheckdigit,  lu_RecordType, ';
                    $sSQL .= '  lu_LastLine,  lu_CarrierRoute,  lu_ReturnCodes,  lu_ErrorCodes, ';
                    $sSQL .= '  lu_ErrorDesc) ';
                    $sSQL .= 'VALUES( ';
                    $sSQL .= " $lu_fam_ID, $lu_LookupDateTime, $lu_DeliveryLine1, ";
                    $sSQL .= " $lu_DeliveryLine2, $lu_City, $lu_State, $lu_ZipAddon, ";
                    $sSQL .= " $lu_Zip, $lu_Addon, $lu_LOTNumber, $lu_DPCCheckdigit, $lu_RecordType, ";
                    $sSQL .= " $lu_LastLine, $lu_CarrierRoute, $lu_ReturnCodes, $lu_ErrorCodes, ";
                    $sSQL .= " $lu_ErrorDesc) ";

                    //echo $sSQL . "<br>";

                    RunQuery($sSQL);
                }

                if ($iSearchesLeft < 30) {
                    if ($lu_ErrorCodes != "'xx'") {
                        echo '<h3>There are '.$iSearchesLeft.' searches remaining ';
                        echo 'in your account.  Searches will be performed one at a time until ';
                        echo 'your account balance is zero.  To enable bulk lookups you will ';
                        echo 'need to add funds to your Intelligent Search Technology account ';
                        echo 'at the following link.<br>';
                        echo '<a href="https://www.intelligentsearch.com/Hosted/User/">';
                        echo 'https://www.intelligentsearch.com/Hosted/User/</a><br></h3>';
                    } else {
                        echo '<h4>Lookup failed.  There is a problem with the connection or with your account.</h4>';
                        echo 'Please verify that your Intelligent Search Technology, Ltd. username and password ';
                        echo 'are correct.<br><br>';
                        echo 'Admin -> Edit General Settings -> sISTusername<br>';
                        echo 'Admin -> Edit General Settings -> sISTpassword<br><br>';
                        echo 'Follow the URL below to log in and manage your Intelligent Search Technology account ';
                        echo 'settings.  If you do not already have an account you may establish an account at this ';
                        echo 'URL. This software was written to work best with the service CorrectAddress(R) ';
                        echo 'with Addons. <br><br><br>';

                        echo '<a href="https://www.intelligentsearch.com/Hosted/User/">https://www.intelligentsearch.com/Hosted/User/</a><br><br>';

                        echo 'If you are sure that your account username and password are correct and that your ';
                        echo 'account is in good standing it is possible that the server is currently unavailable ';
                        echo "but may be back online if you try again later.<br><br>\n";
                    }

                    if ($iSearchesLeft) {
                        ?>
          <form method="GET" action="USISTAddressVerification.php">
            <input type=submit class=btn name=DoLookup value="Perform Next Lookup">
          </form><br><br>
          <?php
                    }
                    $bNormalFinish = false;
                    break;
                }

                $now = time();    // This code used to prevent browser and server timeouts
      // Keep doing fresh reloads of this page until complete.
      if ($now - $startTime > 17) {  // run for 17 seconds, then reload page
        // total cycle is about 20 seconds per page reload
        ?><meta http-equiv="refresh" content="2;URL=USISTAddressVerification.php?DoLookup=Perform+Lookups" /><?php
        $bNormalFinish = false;
          break;
      }
            }
            if ($bNormalFinish) {
                ?><meta http-equiv="refresh" content="2;URL=USISTAddressVerification.php" /><?php
            }
        } ?>
  <table><tr>
  <?php
  if (!$_GET['DoLookup'] && $iEligible) {
      ?>
        <td><form method="GET" action="USISTAddressVerification.php">
            <input type=submit class=btn name=DoLookup value="Perform Lookups">
          </form></td>
  <?php
  } ?>

  <?php if ($iUSOkay) {
      ?>
        <td><form method="POST" action="Reports/USISTAddressReport.php">
            <input type=submit class=btn name=MismatchReport value="View Mismatch Report">
          </form></td>
  <?php
  } ?>

  <?php if ($iNonUSCount) {
      ?>
        <td><form method="POST" action="Reports/USISTAddressReport.php">
            <input type=submit class=btn name=NonUSReport value="View Non-US Address Report">
          </form></td>
  <?php
  } ?>

    </tr></table>

  <?php
    }
require 'Include/Footer.php';
?>
