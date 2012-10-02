#!/bin/bash
# Update domain next-gen by fufroma

# Usefull for debug
#set -x

for CONFIG_FILE in \
      /etc/alternc/local.sh \
      /usr/lib/alternc/functions.sh \
      /usr/lib/alternc/functions_hosting.sh \
      /usr/lib/alternc/functions_dns.sh
  do
    if [ ! -r "$CONFIG_FILE" ]; then
        echo "Can't access $CONFIG_FILE."
        exit 1
    fi
    . "$CONFIG_FILE"
done

# Some vars
umask 022
LOCK_FILE="$ALTERNC_LOC/bureau/cron.lock"
OLDIFS="$IFS"
NEWIFS=" "
RELOAD_ZONES="$(mktemp /tmp/alternc_reload_zones.XXXX)"
RELOAD_WEB="$(mktemp /tmp/alternc_reload_web.XXXX)"
DNS_DO_RESTART="/tmp/alternc.do_do_restart.$$"
B="µµ§§" # Strange letters to make split in query

echo "" > "$RELOAD_ZONES"
echo "" > "$RELOAD_WEB"

# Somes check before start operations
if [ `id -u` -ne 0 ]; then
    log_error "must be launched as root"
elif [ -z "$DEFAULT_MX" -o -z "$PUBLIC_IP" ]; then
    log_error "Bad configuration. Please use: dpkg-reconfigure alternc"
elif [ -f "$LOCK_FILE" ]; then
    process=$(ps f -p `cat "$LOCK_FILE"|tail -1`|tail -1|awk '{print $NF;}')
    if [ "$(basename $process)" = "$(basename "$0")" ] ; then
      log_error "last cron unfinished or stale lock file ($LOCK_FILE)."
    else
      rm "$LOCK_FILE"
    fi
fi

# backward compatibility: single-server setup
if [ -z "$ALTERNC_SLAVES" ] ; then
    ALTERNC_SLAVES="localhost"
fi

# We lock the application
echo $$ > "$LOCK_FILE"

# For domains we want to delete completely, make sure all the tags are all right
# set sub_domaines.web_action = delete where domaines.dns_action = DELETE
mysql_query "update sub_domaines sd, domaines d set sd.web_action = 'DELETE' where sd.domaine = d.domaine and sd.compte=d.compte and d.dns_action = 'DELETE';"

# Sub_domaines we want to delete
# sub_domaines.web_action = delete
for sub in $( mysql_query "select concat_ws('$B',lower(sd.type), if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine)) from sub_domaines sd, domaines_type dt where lower(sd.type)=lower(dt.name) and dt.only_dns = false and web_action ='DELETE';") ; do
    host_delete ${sub/$B/ }
    mysql_query "delete from sub_domaines where concat_ws('$B',lower(type), if(length(sub)>0,concat_ws('.',sub,domaine),domaine)) = '$sub' and web_action ='DELETE';"
    echo 1 > "$RELOAD_WEB"
done

# Sub domaines we want to update
# sub_domaines.web_action = update and sub_domains.only_dns = false
IFS="$NEWIFS"
mysql_query "
select 
  concat_ws('$IFS',lower(sd.type), if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine), sd.valeur )
from 
  sub_domaines sd, 
  domaines_type dt
where 
  lower(sd.type)=lower(dt.name) and 
  dt.only_dns = false and 
  sd.web_action ='UPDATE'
;"|while read type domain valeur ; do
    host_create "$type" "$domain" "$valeur"
    mysql_query "update sub_domaines sd set web_action='OK',web_result='$?' where lower(sd.type)='$type' and if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine)='$domain' and sd.valeur='$valeur'; "
    echo 1 > "$RELOAD_WEB"
done

# Domaine to enable
mysql_query "
select 
  concat_ws('$IFS',lower(sd.type),if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine),sd.valeur) 
from 
  sub_domaines sd, 
  domaines_type dt 
where 
  lower(sd.type)=lower(dt.name) and 
  dt.only_dns = false and
  sd.enable ='ENABLE' 
;"|while read type domain valeur ; do
    host_enable "$type" "$domain" "$valeur"
    mysql_query "update sub_domaines sd set enable='ENABLED' where lower(sd.type)='$type' and if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine)='$domain' and sd.valeur='$valeur';"
    echo 1 > "$RELOAD_WEB"
done

# Domains to disable
mysql_query "
select 
  concat_ws('$IFS',lower(sd.type),if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine),sd.valeur) 
from 
  sub_domaines sd, 
  domaines_type dt
where 
  lower(sd.type)=lower(dt.name) and 
  dt.only_dns = false and
  sd.enable ='DISABLE' 
;"|while read type domain valeur ; do
    host_disable "$type" "$domain" "$valeur"
    mysql_query "update sub_domaines sd set enable='DISABLED' where lower(sd.type)='$type' and if(length(sd.sub)>0,concat_ws('.',sd.sub,sd.domaine),sd.domaine)='$domain' and sd.valeur='$valeur';"
    echo 1 > "$RELOAD_WEB"
done

# Delete entry when the entry in only-dns. We do not need to launch any hooks or other things
# If someone need a hooks on a dns-only entry, he'll uncheck the dns-only checkbox and will write his hooks with a brain (his own brain eventually).
mysql_query "delete sd from sub_domaines sd, domaines_type dt where lower(sd.type)=lower(dt.name) and dt.only_dns = true and sd.web_action ='DELETE';"

# We do not set directly to ENABLED or DISABLED because the cron is needed to do an action on the dns
# even if no actions are done on the sub_domaine itself
# Update only-dns entries.
mysql_query "update sub_domaines sd, domaines_type dt set sd.web_action='OK' where lower(sd.type)=lower(dt.name) and dt.only_dns = true and sd.web_action ='UPDATE';"

# Enable only-dns entries.
mysql_query "update sub_domaines sd, domaines_type dt set sd.enable='ENABLED' where lower(sd.type)=lower(dt.name) and dt.only_dns = true and sd.enable ='ENABLE';"

# Disable only-dns entries.
mysql_query "update sub_domaines sd, domaines_type dt set sd.enable='DISABLED' where lower(sd.type)=lower(dt.name) and dt.only_dns = true and sd.enable ='DISABLE';"

# Domains we do not want to be the DNS serveur anymore :
# domaines.dns_action = UPDATE and domaines.gesdns = 0
for dom in `mysql_query "select domaine from domaines where dns_action = 'UPDATE' and gesdns = 0;"| tr '\n' ' '`
do
    dns_delete $dom
    mysql_query "update domaines set dns_action = 'OK', dns_result = '$?' where domaine = '$dom'"
    echo -n " $dom " >> "$RELOAD_ZONES"
done

# Domains we have to update the dns :
# domaines.dns_action = UPDATE
for dom in `mysql_query "select domaine from domaines where dns_action = 'UPDATE';" | tr '\n' ' '`
do
    echo "dns_regenerate : domain=/$dom/"
    dns_regenerate $dom
    mysql_query "update domaines set dns_action = 'OK', dns_result = '$?' where domaine = '$dom'"
    echo -n " $dom " >> "$RELOAD_ZONES"
done

# Domains we want to delete completely, now we do it
# domaines.dns_action = DELETE
for dom in `mysql_query "select domaine from domaines where dns_action = 'DELETE';" | tr '\n' ' '`
do
    dns_delete $dom
    # Web configurations have already bean cleaned previously
    mysql_query "delete from sub_domaines where domaine='$dom'; delete from domaines where domaine='$dom';"
    echo -n " $dom " >> "$RELOAD_ZONES"
done


if [ ! -z "$(cat "$RELOAD_WEB")" ] ; then
  echo " apache " >> "$RELOAD_ZONES"

  # Concat the apaches files
  tempo=$(mktemp "$VHOST_FILE.XXXXX")
  find "$VHOST_DIR" -mindepth 2 -type f -iname "*.conf" -exec cat '{}' > "$tempo" \;
  if [ $? -ne 0 ] ; then
    log_error " web file concatenation failed"
  fi
  touch "$VHOST_FILE"
  if [ ! -w "$VHOST_FILE" ] ; then
    log_error "cannot write on $VHOST_FILE"
  fi
  mv "$tempo" "$VHOST_FILE"

fi

# What do we reload ?
lst_zones=$(cat "$RELOAD_ZONES"|tr '\n' ' ')
if [ -e "$DNS_DO_RESTART" ] ; then
  lst_zones="dns_daemon $lst_zones" 
fi

# we assume we run apache and bind on the master
/usr/bin/alternc_reload $lst_zones || true
for slave in $ALTERNC_SLAVES; do
    if [ "$slave" != "localhost" ]; then
        ssh alternc@$slave alternc_reload $lst_zones || true
    fi
done

rm -f "$LOCK_FILE" "$RELOAD_ZONES" "$RELOAD_WEB" "$DNS_DO_RESTART"

exit 0

