<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use App\Models\IPv4BgpEntry;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4Peer;
use App\Models\IPv6BgpPrefix;
use App\Models\IPv6Peer;
use App\Models\IX;
use App\Models\IXMember;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\ApiBaseController;

class ApiV1Controller extends ApiBaseController
{
    /*
     * URI: /asn/{as_number}
     * Optional Params: with_raw_whois
     * Optional Params: with_peers
     * Optional Params: with_prefixes
     * Optional Params: with_ixs
     * Optional Params: with_upstreams
     */
    public function asn(Request $request, $as_number)
    {
        // lets only use the AS number.
        $as_number = $this->ipUtils->normalizeInput($as_number);

        $asnData = ASN::with('emails')->where('asn', $as_number)->first();

        if (is_null($asnData)) {
            $data = $this->makeStatus('Could not find ASN', false);
            return $this->respond($data);
        }

        $output['asn']  = $asnData->asn;
        $output['name'] = $asnData->name;
        $output['description_short'] = $asnData->description;
        $output['description_full']  = $asnData->description_full;
        $output['country_code']         = $asnData->counrty_code;
        $output['website']              = $asnData->website;
        $output['email_contacts']       = $asnData->email_contacts;
        $output['abuse_contacts']       = $asnData->abuse_contacts;
        $output['looking_glass']        = $asnData->looking_glass;
        $output['traffic_estimation']   = $asnData->traffic_estimation;
        $output['traffic_ratio']        = $asnData->traffic_ratio;
        $output['owner_address']        = $asnData->owner_address;

        if ($request->has('with_ixs') === true) {
            $output['internet_exchanges'] = IXMember::getMembers($asnData->asn);
        }
        if ($request->has('with_peers') === true) {
            $output['peers'] = ASN::getPeers($as_number);
        }
        if ($request->has('with_prefixes') === true) {
            $output['prefixes'] = ASN::getPrefixes($as_number);
        }
        if ($request->has('with_upstreams') === true) {
            $output['upstreams'] = ASN::getUpstream($as_number);
        }
        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $asnData->raw_whois;
        }

        $output['date_updated']        = (string) $asnData->updated_at;
        return $this->sendData($output);
    }

    /*
     * URI: /asn/{as_number}/peers
     */
    public function asnPeers($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $peers      = ASN::getPeers($as_number);

        return $this->sendData($peers);
    }

    /*
     * URI: /asn/{as_number}/ixs
     */
    public function asnIxs($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $ixs        = IXMember::getMembers($as_number);

        return $this->sendData($ixs);
    }

    /*
     * URI: /asn/{as_number}/prefixes
     */
    public function asnPrefixes($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $prefixes   = ASN::getPrefixes($as_number);

        return $this->sendData($prefixes);
    }

    /*
     * URI: /asn/{as_number}/upstreams
     */
    public function asnUpstreams($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $upstreams  = ASN::getUpstream($as_number);

        return $this->sendData($upstreams);
    }

    /*
     * URI: /prefix/{ip}/{cidr}
     * Optional Params: with_raw_whois
     */
    public function prefix(Request $request, $ip, $cidr)
    {
        $ipVersion = $this->ipUtils->getInputType($ip);

        if ($ipVersion === 4) {
            $prefix = IPv4BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->first();
        } else if ($ipVersion === 6) {
            $prefix = IPv6BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->first();
        } else {
            $data = $this->makeStatus('Malformed input', false);
            return $this->respond($data);
        }

        if (is_null($prefix) === true) {
            $data = $this->makeStatus('Could not find prefix in BGP table', false);
            return $this->respond($data);
        }

        $prefixWhois = $prefix->whois();
        $allocation = $this->ipUtils->getAllocationEntry($prefix->ip);
        $geoip = $this->ipUtils->geoip($prefix->ip);

        $output['prefix']           = $prefix->ip . '/' . $prefix->cidr;
        $output['ip']               = $prefix->ip;
        $output['cidr']             = $prefix->cidr;
        $output['asn']              = $prefix->asn;
        $output['name']             = $prefixWhois ? $prefixWhois->name : null;
        $output['description_short']= $prefixWhois ? $prefixWhois->description : null;
        $output['description_full'] = $prefixWhois ? $prefixWhois->description_full : null;
        $output['emails']           = $prefixWhois ? $prefixWhois->email_contacts : null;
        $output['abuse_emails']     = $prefixWhois ? $prefixWhois->abuse_contacts : null;
        $output['owner_address']    = $prefixWhois ? $prefixWhois->owner_address : null;

        $output['country_codes']['whois_country_code']          = $prefixWhois ? $prefixWhois->counrty_code : null;
        $output['country_codes']['rir_allocation_country_code'] = $allocation ? $allocation->counrty_code : null;
        $output['country_codes']['maxmind_country_code']        = $geoip->country->isoCode ?: null;

        $output['rir_allocation']['rir_name']           = $allocation->rir->name;
        $output['rir_allocation']['country_code']       = $allocation->counrty_code;
        $output['rir_allocation']['ip']                 = $allocation->ip;
        $output['rir_allocation']['cidr']               = $allocation->cidr;
        $output['rir_allocation']['prefix']             = $allocation->ip . '/' . $allocation->cidr;
        $output['rir_allocation']['date_allocated']     = $allocation->date_allocated . ' 00:00:00';

        $output['maxmind']['country_code']  = $geoip->country->isoCode ?: null;
        $output['maxmind']['city']          = $geoip->city->name ?: null;

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $prefixWhois ? $prefixWhois->raw_whois : null;
        }

        $output['date_updated']   = (string) ($prefixWhois ? $prefixWhois->updated_at : $prefix->updated_at);

        return $this->sendData($output);
    }

    /*
     * URI: /ip/{ip}
     */
    public function ip($ip)
    {
        $prefixes = $this->ipUtils->getBgpPrefixes($ip);
        $geoip = $this->ipUtils->geoip($ip);
        $allocation = $this->ipUtils->getAllocationEntry($ip);

        $output['prefixes'] = [];
        foreach ($prefixes as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']             = $prefix->ip;
            $prefixOutput['cidr']           = $prefix->cidr;
            $prefixOutput['asn']            = $prefix->asn;
            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['prefixes'][]  = $prefixOutput;
        }

        $output['rir_allocation']['rir_name']           = $allocation->rir->name;
        $output['rir_allocation']['country_code']       = $allocation->counrty_code;
        $output['rir_allocation']['ip']                 = $allocation->ip;
        $output['rir_allocation']['cidr']               = $allocation->cidr;
        $output['rir_allocation']['prefix']             = $allocation->ip . '/' . $allocation->cidr;
        $output['rir_allocation']['date_allocated']     = $allocation->date_allocated . ' 00:00:00';

        $output['maxmind']['country_code']  = $geoip->country->isoCode ?: null;
        $output['maxmind']['city']          = $geoip->city->name ?: null;

        return $this->sendData($output);
    }

    /*
     * URI: /ix/{ix_id}
     */
    public function ix($ix_id)
    {
        $ix = IX::find($ix_id);

        if (is_null($ix) === true) {
            $data = $this->makeStatus('Could not find IX', false);
            return $this->respond($data);
        }

        $output['name']         = $ix->name;
        $output['name_full']    = $ix->name_full;
        $output['website']      = $ix->website;
        $output['tech_email']   = $ix->tech_email;
        $output['tech_phone']   = $ix->tech_phone;
        $output['policy_email'] = $ix->policy_email;
        $output['policy_phone'] = $ix->policy_phone;
        $output['city']         = $ix->city;
        $output['counrty_code'] = $ix->counrty_code;
        $output['url_stats']    = $ix->url_stats;

        $members = [];
        foreach ($ix->members as $member) {
            $asnInfo = $member->asn_info;

            $memberInfo['asn']          = $asnInfo ? $asnInfo->asn : null;
            $memberInfo['name']         = $asnInfo ? $asnInfo->name: null;
            $memberInfo['description']  = $asnInfo ? $asnInfo->description : null;
            $memberInfo['counrty_code'] = $asnInfo ? $asnInfo->counrty_code : null;
            $memberInfo['ipv4_address'] = $member->ipv4_address;
            $memberInfo['ipv6_address'] = $member->ipv6_address;
            $memberInfo['speed']        = $member->speed;

            $members[] = $memberInfo;
        }

        $output['members_count'] = count($members);
        $output['members'] = $members;

        return $this->sendData($output);
    }
}