<?php

/**
 * Roundcube Password Driver for Plesk-RPC.
 *
 * This driver changes a E-Mail-Password via Plesk-RPC
 * Deps: PHP-Curl, SimpleXML
 *
 * @author     Cyrill von Wattenwyl <cyrill.vonwattenwyl@adfinis-sygroup.ch>
 * @copyright  Adfinis SyGroup AG, 2014
 *
 * Config needed:
 * $config['password_plesk_host']     = '10.0.0.5';
 * $config['password_plesk_user']     = 'admin';
 * $config['password_plesk_pass']     = 'pass';
 * $config['password_plesk_rpc_port'] = 8443;
 * $config['password_plesk_rpc_path'] = enterprise/control/agent.php;
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://www.gnu.org/licenses/.
 */

/**
 * Roundcube Password Driver Class
 *
 * See {ROUNDCUBE_ROOT}/plugins/password/README for API description
 *
 * @author Cyrill von Wattenwyl <cyrill.vonwattenwyl@adfinis-sygroup.ch>
 */
class rcube_plesk_password
{
    /**
     * This method is called from roundcube to change the password
     *
     * roundcube already validated the old password so we just need to change it at this point
     *
     * @author Cyrill von Wattenwyl <cyrill.vonwattenwyl@adfinis-sygroup.ch>
     * @param string $currpass Current password
     * @param string $newpass  New password
     * @return int PASSWORD_SUCCESS|PASSWORD_ERROR
     */
    function save($currpass, $newpass, $username)
    {
        // get config
        $rcmail = rcmail::get_instance();
        $host   = $rcmail->config->get('password_plesk_host');
        $user   = $rcmail->config->get('password_plesk_user');
        $pass   = $rcmail->config->get('password_plesk_pass');
        $port   = $rcmail->config->get('password_plesk_rpc_port');
        $path   = $rcmail->config->get('password_plesk_rpc_path');

        // create plesk-object
        $plesk = new plesk_rpc;
        $plesk->init($host, $port, $path, $user, $pass);

        // try to change password and return the status
        $result = $plesk->change_mailbox_password($username, $newpass);
        //$plesk->destroy();

        if ($result == "ok") {
            return PASSWORD_SUCCESS;
        } elseif (is_array($result)) {
            return $result;
        }

        return PASSWORD_ERROR;
    }
}


/**
 * Plesk RPC-Class
 *
 * Striped down version of Plesk-RPC-Class
 * Just functions for changing mail-passwords included
 *
 * Documentation of Plesk RPC-API: http://download1.parallels.com/Plesk/PP11/11.0/Doc/en-US/online/plesk-api-rpc/
 *
 * @author Cyrill von Wattenwyl <cyrill.vonwattenwyl@adfinis-sygroup.ch>
 */
class plesk_rpc
{
    protected $old_version = false;
    protected $curl;

    /**
     * init plesk-rpc via curl
     *
     * @param string $host plesk host
     * @param string $port plesk rpc port
     * @param string $path plesk rpc path
     * @param string $user plesk user
     * @param string $user plesk password
     * @returns void
     */
    function init($host, $port, $path, $user, $pass)
    {
        $headers = [
            sprintf("HTTP_AUTH_LOGIN: %s", $user),
            sprintf("HTTP_AUTH_PASSWD: %s", $pass),
            "Content-Type: text/xml"
        ];

        $url        = sprintf("https://%s:%s/%s", $host, $port, $path);
        $this->curl = curl_init();

        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST , 0);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER , false);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER     , $headers);
        curl_setopt($this->curl, CURLOPT_URL            , $url);
    }

    /**
     * send a request to the plesk
     *
     * @param string $packet XML-Packet to send to Plesk
     *
     * @returns string Response body
     */
    function send_request($packet)
    {
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $packet);
        $result = curl_exec($this->curl);

        return $result && strpos($result, '<?xml') === 0 ? $result : null;
    }

    /**
     * close curl
     */
    function destroy()
    {
        curl_close($this->curl);
    }

    /**
     * Get all hosting-information of a domain
     *
     * @param string $domain domain-name
     * @returns object SimpleXML object
     */
    function domain_info($domain)
    {
        // build xml
        $request = new SimpleXMLElement("<packet></packet>");
        $site    = $request->addChild("site");
        $get     = $site->addChild("get");
        $filter  = $get->addChild("filter");

        $filter->addChild("name", utf8_encode($domain));
        $dataset = $get->addChild("dataset");

        $dataset->addChild("hosting");
        $packet = $request->asXML();
        $xml    = null;

        // send the request and make it to simple-xml-object
        if ($res = $this->send_request($packet)) {
            $xml = new SimpleXMLElement($res);
        }

        // Old Plesk versions require version attribute, add it and try again
        if ($xml && $xml->site->get->result->status == 'error' && $xml->site->get->result->errcode == 1017) {
            $request->addAttribute("version", "1.6.3.0");
            $packet = $request->asXML();

            $this->old_version = true;

            // send the request and make it to simple-xml-object
            if ($res = $this->send_request($packet)) {
                $xml = new SimpleXMLElement($res);
            }
        }

        return $xml;
    }

    /**
     * Get psa-id of a domain
     *
     * @param string $domain domain-name
     *
     * @returns int Domain ID
     */
    function get_domain_id($domain)
    {
        if ($xml = $this->domain_info($domain)) {
            return intval($xml->site->get->result->id);
        }
    }

    /**
     * Change Password of a mailbox
     *
     * @param string $mailbox full email-address (user@domain.tld)
     * @param string $newpass new password of mailbox
     *
     * @returns bool
     */
    function change_mailbox_password($mailbox, $newpass)
    {
        list($user, $domain) = explode("@", $mailbox);
        $domain_id = $this->get_domain_id($domain);

        // if domain cannot be resolved to an id, do not continue
        if (!$domain_id) {
            return false;
        }

        // build xml-packet
        $request = new SimpleXMLElement("<packet></packet>");
        $mail    = $request->addChild("mail");
        $update  = $mail->addChild("update");
        $add     = $update->addChild("set");
        $filter  = $add->addChild("filter");
        $filter->addChild("site-id", $domain_id);

        $mailname = $filter->addChild("mailname");
        $mailname->addChild("name", $user);

        $password = $mailname->addChild("password");
        $password->addChild("value", $newpass);
        $password->addChild("type", "plain");

        if ($this->old_version) {
            $request->addAttribute("version", "1.6.3.0");
        }

        $packet = $request->asXML();

        // send the request to plesk
        if ($res = $this->send_request($packet)) {
            $xml = new SimpleXMLElement($res);
            $res = strval($xml->mail->update->set->result->status);

            if ($res != "ok") {
                $res = [
                    'code' => PASSWORD_ERROR,
                    'message' => strval($xml->mail->update->set->result->errtext)
                ];
            }

            return $res;
        }

        return false;
    }
}
