<h2>php-infocard-rp</h2>

<?php
require_once 'InfoCard/InfoCard.php';

if (isset($_POST['xmlToken'])) {
    $infocard = new InfoCard();
    $infocard->addCertificatePair('/etc/ssl-keys/server.key',
                                  '/etc/ssl-keys/server.crt');

    $token = stripslashes($_POST['xmlToken']);

    $claims = $infocard->process($token);
    if($claims->isValid()) {
      print "Email: {$claims->emailaddress}<br />";
      print "PPID: {$claims->getCardID()}<br />";
    }
    else {
        print "Error Validating identity: {$claims->getErrorMsg()}";
    }
}
?>

<form method='post' id='infocard'>
  <object type="application/x-informationcard" name="xmlToken">
    <param name="requiredClaims" value="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress" />
    <param name="privacyVersion" value="1"/>
    <param name="tokenType" value="urn:oasis:names:tc:SAML:1.0:assertion"/>
  </object>

  <p>
    This site requests the following claims:
    <ul>
      <li>http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier</li>
      <li>http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress</li>
    </ul>
  </p>

  <button type="submit">
    <img src="infocard_60x42.png" style="padding:10px" />
  </button>
</form>
