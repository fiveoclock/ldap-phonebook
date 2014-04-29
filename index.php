<?php
utf8_encode($str);
//ini_set('display_errors', 1);
//ini_set('log_errors', 1);
//error_reporting(E_ALL);

// settings
$ldap_url = "ldap://server.5dom.net:3268";
$user = "ldap.telcomm.phonebook@5dom.net";
$password = "password";

function ldap_escape($str, $for_dn = false) {
   // copied from http://php.net/manual/en/function.ldap-search.php#90158

   // see:
   // RFC2254
   // http://msdn.microsoft.com/en-us/library/ms675768(VS.85).aspx
   // http://www-03.ibm.com/systems/i/software/ldap/underdn.html

   if  ($for_dn)
      $metaChars = array(',','=', '+', '<','>',';', '\\', '"', '#');
   else
      $metaChars = array('(', ')', '\\', chr(0));

   $quotedMetaChars = array();
   foreach ($metaChars as $key => $value) $quotedMetaChars[$key] = '\\'.str_pad(dechex(ord($value)), 2, '0');
   $str=str_replace($metaChars,$quotedMetaChars,$str); //replace them
   return ($str);
}

?>

<html>
<head>
<title>Phonebook</title>
<link rel="stylesheet" type="text/css" href="style.css">
<script language="JavaScript">

   function copy(text) {
      if(window.clipboardData) {
         text = text.replace(/\(/g, ''); // (
         text = text.replace(/\)/g, ''); // )
         text = text.replace(/\-/g, ''); // -
         text = text.replace(/\//g, ''); // slash
         text = text.replace(/\s+/g, ''); // spaces

         if (document.getElementById('replaceplus').checked) {
            text = text.replace(/\+/g, '000'); // plus
         }
         window.clipboardData.setData('text',text);
      }
   }
</script>

</head>
<body>

<form method="POST">
<table class="form-table">
<tr>
<td>
   Name - last (first):
   <br>
   <input type="text" name="name" length="30" value="*">
</td><td>
   Department:
   <br>
   <input type="text" name="department" length="30" value="*">
</td><td>
   Site:
   <br>
   <input type="text" name="site" length="30" value="*">
</td><td>
   Phone number:
   <br>
   <input type="text" name="phonenumber" length="30" value="*">
</td><td>
   <br>
   <input type="submit" name="submit" value="Search">
</td><td>
   <br>
   <input type="checkbox" name="replaceplus" checked>Replace plus with '000' on copy to clipboard</input>
</td>
</tr>
</table>
</form>


<?php
if (  isset($_POST['name']) &&
      isset($_POST['department']) &&
      isset($_POST['site']) &&
      isset($_POST['phonenumber'])
)
{
   // could be changed to get empty variables instead of "*" for any
   $name = ldap_escape($_POST['name']);
   $department = ldap_escape($_POST['department']);
   $site = ldap_escape($_POST['site']);
   $phonenumber = ldap_escape($_POST['phonenumber']);

   if ($name != "*")
      $name = "*". $name ."*";

   // if "*" don't mention in query
   if ($department != "*") {
      $department_query = "(department=*". $department ."*)";
   }
   else { $department_query = ""; }

   // if "*" don't mention in query
   if ($site != "*") {
      $site_query = "(physicaldeliveryofficename=*". $site ."*)";
   }
   else { $site_query = ""; }


   if ($phonenumber != "*")
      $phonenumber = "*". $phonenumber ."*";


   // specify the LDAP server to connect to
   $conn = ldap_connect($ldap_url) or die("Could not connect to server");
   ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);

   // bind to the LDAP server specified above
   $bind = ldap_bind($conn, $user, $password);

   // create the search string
   $query = "(&
      (|
         (displayName=$name)
         (samaccountname=$name)
      )
      $department_query
      $site_query
      (|
         (telephonenumber=$phonenumber)
         (mobile=$phonenumber)
      )
      (|
         (!(userAccountControl:1.2.840.113556.1.4.803:=2))
         (&(userAccountControl:1.2.840.113556.1.4.803:=2)(extensionAttribute6=1))
      )
   )";

   // start searching
   // specify both the start location and the search criteria
   // in this case, start at the top and return all entries
   $result = ldap_search($conn,"dc=mmdom,dc=net", $query) or die("Error in search query");

   // get entry data as array
   $info = ldap_get_entries($conn, $result);



   // Sort Data by Company, Last Name and First Name
   $attribs = array('physicaldeliveryofficename','sn','givenname');

   for ($i=0; $i<$info["count"]; $i++) {
      $index = $info[$i];
      $j=$i;
      do {
         //create comparison variables from attributes:
         $a = $b = null;
         foreach($attribs as $attrib) {
            $a .= $info[$j-1][$attrib][0];
            $b .= $index[$attrib][0];
         }

         // do the comparison
         if ($a > $b) {
            $is_greater = true;
            $info[$j] = $info[$j-1];
            $j = $j-1;
         }
         else {
            $is_greater = false;
         }
      }
      while ($j>0 && $is_greater);
      $info[$j] = $index;
   }

   // iterate over array and prepare the table
   $table = "";
   for ($i=0; $i<$info["count"]; $i++)
   {
      $table .= "<tr>";
      $table .= "<td>". utf8_decode($info[$i]["displayname"][0]) ."</td>";
      $table .= "<td>". utf8_decode($info[$i]["physicaldeliveryofficename"][0]) ."</td>";
      $table .= "<td>". utf8_decode($info[$i]["department"][0]) ."</td>";
      $table .= "<td>". utf8_decode($info[$i]["title"][0]) ."</td>";
      $table .= "<td onclick='javascript:copy(this.outerText);'><div class=action>".$info[$i]["telephonenumber"][0]."</div></td>";
      $table .= "<td onclick='javascript:copy(this.outerText);'><div class=action>".$info[$i]["mobile"][0]."</div></td>";
      $table .= "</tr>";
   }

   // save number of entries
   $num_entries = ldap_count_entries($conn, $result);

   // all done? clean up
   ldap_close($conn);
}

?>

<!--div style="height: 100%; width: 100%"> -->
<div class="result-div">
<table class="result-table">
   <tr>
   <th>Name</th>
   <th>Site</th>
   <th>Department</th>
   <th>Job title</th>
   <th>Phone</th>
   <th>Mobile</th>
   </tr>
   <?php
   if (isset($table)) echo $table;
   ?>

</table>
</div>

<?php
if (isset($num_entries)) echo $num_entries ." record(s) found. - ";
?>
Note: Phone numbers can be copied to clipboard by clicking on them in Internet Explorer.

<?php
//if (isset($query)) echo $query;
?>

</body>
</html>
