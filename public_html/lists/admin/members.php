
<hr/>

<?php
require_once dirname(__FILE__).'/accesscheck.php';
$access = accessLevel("members");
if (isset($_REQUEST['id'])) {
  $id = sprintf('%d',$_REQUEST["id"]);
} else $id = 0;
if (isset($_GET['s'])) {
  $start = sprintf('%d',$_GET['s']);
} else $start = 0;

switch ($access) {
  case "owner":
    $subselect = " where owner = ".$_SESSION["logindetails"]["id"];
    if ($id) {
      $query = "select id from " . $tables['list'] . $subselect . " and id = ?";
      $rs = Sql_Query_Params($query, array($id));
      if (!Sql_Num_Rows($rs)) {
        Fatal_Error($GLOBALS['I18N']->get("You do not have enough priviliges to view this page"));
        return;
      }
    }
    break;
  case "all":
  case "view":
    $subselect = "";break;
  case "none":
  default:
    if ($id) {
      Fatal_Error($GLOBALS['I18N']->get("You do not have enough priviliges to view this page"));
      return;
    }
    $subselect = " where id = 0";
    break;
}
function addUserForm ($listid) {
//nizar 'value'
  $html = formStart(' class="membersAdd" ').'<input type="hidden" name="listid" value="'.$listid.'" />
  '.$GLOBALS['I18N']->get("Add a user").': <input type="text" name="new" value="" size="40" id="emailsearch"/>
     <input class="submit" type="submit" name="add" value="'.$GLOBALS['I18N']->get('Add').'" />
  </form>';
  return $html;
}
if (!empty($id)) {
  print "<h3>".$GLOBALS['I18N']->get("Members of")." ".ListName($id)."</h3>";
  echo "<br />".PageLink2("editlist",$GLOBALS['I18N']->get("back to this list"),"id=$id");
  echo "<br />".PageLink2("export&amp;list=$id",$GLOBALS['I18N']->get("Download users on this list as a CSV file"));
  echo "<br />".PageLinkDialog("import&amp;list=$id",$GLOBALS['I18N']->get("Import Users to this list"));

#  print addUserForm($id);
} else {
  Redirect('list');
#  Fatal_Error($GLOBALS['I18N']->get("Please enter a listid"));
}

if (!empty($_POST['importcontent'])) {
  include dirname(__FILE__).'/importsimple.php';
}

if (isset($_REQUEST["processtags"]) && $access != "view") {
  print $GLOBALS['I18N']->get("Processing")." .... <br/>";
  if ($_POST["tagaction"] && is_array($_POST["user"])) {
    switch ($_POST["tagaction"]) {
      case "move":
        $cnt = 0;
        foreach ($_POST["user"] as $key => $val) {
          Sql_query("delete from {$tables["listuser"]} where listid = $id and userid = $key");
          Sql_query("replace into {$tables["listuser"]} (listid,userid) values({$_POST["movedestination"]},$key)");
          if (Sql_Affected_rows() == 1) # 2 means they were already on the list
            $cnt++;
        }
        $msg = $cnt .' '.$GLOBALS['I18N']->get("users were moved to").' '.listName($_POST["movedestination"]);
        break;
      case "copy":
        $cnt = 0;
        foreach ($_POST["user"] as $key => $val) {
          Sql_query("replace into {$tables["listuser"]} (listid,userid)
            values({$_POST["copydestination"]},$key)");
          $cnt++;
        }
        $msg = $cnt .' '.$GLOBALS['I18N']->get("users were copied to").' '.listName($_POST["copydestination"]);
        break;
      case "delete":
        $cnt = 0;
        foreach ($_POST["user"] as $key => $val) {
          Sql_query("delete from {$tables["listuser"]} where listid = $id and userid =
            $key");
          if (Sql_Affected_rows())
            $cnt++;
        }
        $msg = $cnt.' '.$GLOBALS['I18N']->get("users were deleted from this list");
        break;
      default: # do nothing
        break;
    }
  }
  if ($_POST["tagaction_all"] != "nothing") {
    $query = sprintf('select userid from %s where listid = ?', $tables['listuser']);
    $req = Sql_Query_Params($query, array($id));
    switch ($_POST["tagaction_all"]) {
      case "move":
        $cnt = 0;
        while ($user = Sql_Fetch_Row($req)) {
          Sql_query("delete from {$tables["listuser"]} where listid = $id and userid =
            $user[0]");
          Sql_query("replace into {$tables["listuser"]} (listid,userid)
            values({$_POST["movedestination_all"]},$user[0])");
          if (Sql_Affected_rows() == 1) # 2 means they were already on the list
            $cnt++;
        }
        $msg = $cnt . ' '.$GLOBALS['I18N']->get("users were moved to").' '.listName($_POST["movedestination_all"]);
        break;
      case "copy":
        $cnt = 0;
        while ($user = Sql_Fetch_Row($req)) {
          Sql_query("replace into {$tables["listuser"]} (listid,userid)
            values({$_POST["copydestination_all"]},$user[0])");
          $cnt++;
        }
        $msg = $cnt .' '.$GLOBALS['I18N']->get("users were copied to").' '.listName($_POST["copydestination_all"]);
        break;
      case "delete":
        Sql_Query(sprintf('delete from %s where listid = %d',$tables["listuser"],$id));
        $msg = Sql_Affected_Rows().' '.$GLOBALS['I18N']->get("users were deleted from this list");
        break;
      default: # do nothing
    }
  }
  print $msg.'<br/>';
}
if (isset($_POST["add"])) {
  if ($_POST["new"]) {
    $result = Sql_query(sprintf('select * from %s where email = "%s"',$tables["user"],$_POST["new"]));
    if (Sql_affected_rows()) {
      print "<p>".$GLOBALS['I18N']->get("Users found, click add to add this user").":<br /><ul>\n";
      while ($user = Sql_fetch_array($result)) {
        printf ("<li>[ ".PageLink2("members",$GLOBALS['I18N']->get("Add"),"add=1&amp;id=$id&amp;doadd=".$user["id"]).' ] %s </li>',
 $user["email"]);
      }
      print "</ul>\n";
    } else {
      print '<p class="information">'.$GLOBALS['I18N']->get("No user found with that email").'</p><table class="membersForm">'.formStart(' class="membersSubscribe" ');
      require $GLOBALS["coderoot"] . "subscribelib2.php";
      ?>
      <?php
      # pass the entered email on to the form
      $_REQUEST["email"] = $_POST["new"];
  /*      printf('
        <tr><td><div class="required">%s</div></td>
        <td class="attributeinput"><input type="text" name="email" value="%s" size="%d">
        <script language="Javascript" type="text/javascript">addFieldToCheck("email","%s");</script></td></tr>',
        $strEmail,$email,$textlinewidth,$strEmail);
  */
        print ListAllAttributes();
      ?>
      <!--nizar 5 lignes -->
      <tr><td colspan=2><input type=hidden name=action value="insert"><input
 type=hidden name=doadd value="yes"><input type=hidden name=id value="<?php echo
 $id ?>"><input type=submit name=subscribe value="<?php echo $GLOBALS['I18N']->get('add user')?>"></form></td></tr></table>
      <?php
      return;
    }
  }
}
if (isset($_REQUEST["doadd"])) {
  if ($_POST["action"] == "insert") {
    $email = trim($_POST["email"]);
    #TODO validate email address.
    print $GLOBALS['I18N']->get("Inserting user")." $email";
    $result = Sql_query(sprintf('
      insert into %s (email,entered,confirmed,htmlemail,uniqid)
       values("%s",now(),1,%d,"%s")',
      $tables["user"],$email,!empty($_POST['htmlemail']) ? '1':'0',getUniqid()));
    $userid = Sql_insert_id();
    $query = "insert into $tables[listuser] (userid,listid,entered)
 values($userid,$id,now())";
    $result = Sql_query($query);
    # remember the users attributes
    $res = Sql_Query("select * from $tables[attribute]");
    while ($row = Sql_Fetch_Array($res)) {
      $fieldname = "attribute" .$row["id"];
      $value = $_POST[$fieldname];
      if (is_array($value)) {
        $newval = array();
        foreach ($value as $val) {
          array_push($newval,sprintf('%0'.$checkboxgroup_storesize.'d',$val));
        }
        $value = join(",",$newval);
      }
      $res1 = Sql_Replace($tables['user_attribute'], array('attributeid' => $row['id'], 'userid' => $userid, 'value' => $value), 'id');
    }
  } else {
    $res2 = Sql_Replace($tables['listuser'], array('userid' => "'" . $_REQUEST['doadd'] . "'", 'listid' => $id, 'entered' => 'current_timestamp'), array('userid', 'listid'), false);
  }

  if ($database_module == 'adodb.inc')
  Sql_Commit_Transaction();

  echo "<br />".$GLOBALS['I18N']->get("User added")."<br />";
}
if (isset($_REQUEST["delete"])) {
  $delete = sprintf('%d',$_REQUEST["delete"]);
  # single delete the index in delete
  print $GLOBALS['I18N']->get("Deleting")." $delete ..\n";
  $query
  = ' delete from ' . $tables['listuser']
  . ' where listid = ?'
  . '   and userid = ?';
  $result = Sql_Query_Params($query, array($id, $delete));
  print "... ".$GLOBALS['I18N']->get("Done")."<br />\n";
  Redirect("members&id=$id");
}
if (isset($id)) {
  $query
  = ' select count(*)'
  . ' from %s lu'
  . '    join %s u'
  . '       on lu.userid = u.id'
  . ' where lu.listid = ?';
  $query = sprintf($query, $tables['listuser'], $tables['user']);
  $result = Sql_Query_Params($query, array($id));
  $row = Sql_Fetch_row($result);
  $total = $row[0];
  print "$total ".$GLOBALS['I18N']->get("Users on this list");
  $offset = $start;

  if ($total > MAX_USER_PP) {
      if ($start > 0) {
        $listing = $GLOBALS['I18N']->get("Listing user")." $start ".$GLOBALS['I18N']->get("to")." " . ($start + MAX_USER_PP);
        $limit = "limit $start,".MAX_USER_PP;
     } else {
        $listing = $GLOBALS['I18N']->get("Listing user 1 to 50");
        $limit = "limit 0,50";
      }

      print '<p>'.$listing.'</p>';
      print Paging(PageUrl2("members&id=$id"),$start,$total,MAX_USER_PP);
  }
//  $result = Sql_query("SELECT $tables[user].id,email,confirmed,rssfrequency FROM // so plugins can use all fields
  $query
  = ' select u.*'
  . " from %s lu"
  . "    join %s u"
  . '       on lu.userid = u.id'
  . ' where lu.listid = ?'
  . ' order by confirmed desc, email'
  . ' limit ' . MAX_USER_PP . ' offset ' . $offset;
// TODO Consider using a subselect.  select user where uid in select uid from list
  $query=sprintf($query, $tables['listuser'], $tables['user'] );
  $result = Sql_Query_Params($query, array($id));
  print formStart(' name="users" class="membersProcess" ');
  printf('<input type="hidden" name="id" value="%d" />',$id);
  ?>

  <input type="checkbox" name="checkall" class="checkallcheckboxes" /><?php echo $GLOBALS['I18N']->get("Tag all users in this page");?>
  <?php
  $columns = array();
  $columns = explode(',',getConfig('membership_columns'));
 # $columns = array('country','Lastname');
  $ls = new WebblerListing($GLOBALS['I18N']->get("Members"));
  while ($user = Sql_fetch_array($result)) {
    $ls->addElement($user["email"],PageUrl2("user&amp;id=".$user["id"]));
    $ls->addColumn($user["email"],$GLOBALS['I18N']->get("confirmed"),$user["confirmed"]?$GLOBALS["img_tick"]:$GLOBALS["img_cross"]);
    if ($access != "view")
    $ls->addColumn($user["email"],$GLOBALS['I18N']->get("tag"),sprintf('<input type="checkbox" name="user[%d]" value="1" />',$user["id"]));
    if ($access != "view")
       $ls->addColumn($user["email"],$GLOBALS['I18N']->get("del"),
       sprintf('<a href="javascript:deleteRec(\'%s\');">'.$GLOBALS['I18N']->get('Del').'</a>',
       PageURL2("members","","start=$start&id=$id&delete=".$user["id"])));
/*
    $query
    = ' select count(*)'
    . ' from %s lm, %s um'
    . ' where lm.messageid = um.messageid'
    . '   and lm.listid = ?'
    . '   and um.userid = ?';
    // TODO: Could left join with above query.
    $query = sprintf($query, $tables['listmessage'], $tables['usermessage']);
    $rs = Sql_Query_Params($query, array($id, $user['id']));
    $msgcount = Sql_Fetch_Row($rs);
    $ls->addColumn($user["email"],$GLOBALS['I18N']->get("# msgs"),$msgcount[0]);
*/

    ## allow plugins to add columns
    foreach ($GLOBALS['plugins'] as $plugin) {
      $plugin->displayUsers($user,  $user['email'], $ls);
    }

    if (sizeof($columns)) {
      # let's not do this when not required, adds rather many db requests
#      $attributes = getUserAttributeValues('',$user['id']);
#      foreach ($attributes as $key => $val) {
#          $ls->addColumn($user["email"],$key,$val);
#      }

      foreach ($columns as $column) {
        if (isset($attributes[$column]) && $attributes[$column]) {
          $ls->addColumn($user["email"],$column,$attributes[$column]);
        }
      }
    }
  }
  print $ls->display();
}
if ($access == "view") return;
?>
<hr/>
<table class="membersProcess">
<tr><td colspan="2"><h3><?php echo $GLOBALS['I18N']->get('What to do with "Tagged" users')?>:</h3>
<?php echo $GLOBALS['I18N']->get('This will only process the users in this page that have the "Tag" checkbox checked')?></td></tr>
<tr><td colspan="2"><?php echo $GLOBALS['I18N']->get('delete')?> (<?php echo $GLOBALS['I18N']->get('from this list')?>)
<input type="radio" name="tagaction" value="delete" /></td></tr>
<?php
$html = '';
$res = Sql_Query("select id,name from {$tables["list"]} $subselect");
while ($row = Sql_Fetch_array($res)) {
  if ($row["id"] != $id)
    $html .= sprintf('    <option value="%d">%s</option>',$row["id"],$row["name"]);
}
if ($html) {
?>
  <tr><td><?php echo $GLOBALS['I18N']->get('move')?> <input type="radio" name="tagaction" value="move" /> </td><td><?php echo $GLOBALS['I18N']->get('to')?>
 <select name="movedestination">
  <?php echo $html ?>
</select></td></tr>
  <tr><td><?php echo $GLOBALS['I18N']->get('copy')?> <input type="radio" name="tagaction" value="copy" /> </td><td><?php echo $GLOBALS['I18N']->get('to')?>
 <select name="copydestination">
  <?php echo $html ?>
</select></td></tr>
<tr><td colspan="2"><?php echo $GLOBALS['I18N']->get('nothing')?> <input type="radio" name="tagaction" value="nothing" checked="checked" /></td></tr>
<?php } ?>
<tr><td colspan="2"><hr/></td></tr>
<tr><td colspan="2"><h3><?php echo $GLOBALS['I18N']->get('What to do with all users')?></h3>
            <?php echo $GLOBALS['I18N']->get('This will process all users on this list')?></td></tr>
<tr><td colspan="2"><?php echo $GLOBALS['I18N']->get('delete')?> (<?php echo $GLOBALS['I18N']->get('from this list')?>)
       <input type="radio" name="tagaction_all" value="delete" /></td>
</tr>
<?php if ($html) { ?>
  <tr><td><?php echo $GLOBALS['I18N']->get('move')?> <input type="radio" name="tagaction_all" value="move" /> </td><td><?php echo $GLOBALS['I18N']->get('to')?>
 <select name="movedestination_all">
  <?php echo $html ?>
</select></td></tr>
  <tr><td><?php echo $GLOBALS['I18N']->get('copy')?> <input type="radio" name="tagaction_all" value="copy" /> </td><td><?php echo $GLOBALS['I18N']->get('to')?>
 <select name="copydestination_all">
  <?php echo $html ?>
</select></td></tr>
<tr><td colspan="2"><?php echo $GLOBALS['I18N']->get('nothing')?> <input type="radio" name="tagaction_all"  value="nothing" checked="checked" /></td></tr>
<?php } ?>
<tr><td colspan="2"><input class="submit" type="submit" name="processtags" value="<?php echo $GLOBALS['I18N']->get('do it')?>" /></td></tr>
</table>
</form>
