<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class DocumentBranding
{
    public function resolve(): array
    {
        $accent=(string)Setting::get('document_brand_color','#277967');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/',$accent)) $accent='#277967';
        $accent=strtoupper(substr($accent,1));
        $mix=function(float $white) use($accent): string {
            $result='';
            foreach (str_split($accent,2) as $channel) $result.=sprintf('%02X',round(($white < 0 ? hexdec($channel)*(1+$white) : hexdec($channel)*(1-$white)+255*$white)));
            return $result;
        };
        $logo=public_path('images/system-logo.png');
        $stored=(string)Setting::get('site_logo','');
        if (preg_match('~^branding/[a-zA-Z0-9_./-]+\.(png|jpe?g)$~i',$stored)) {
            $root=realpath(Storage::disk('public')->path(''));
            $candidate=realpath(Storage::disk('public')->path($stored));
            if ($root && $candidate && str_starts_with(strtolower($candidate),strtolower($root.DIRECTORY_SEPARATOR))) $logo=$candidate;
        }
        $info=is_file($logo) ? @getimagesize($logo) : false;
        if (!$info || !in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true)) {
            $logo=public_path('images/system-logo.png');
            $info=is_file($logo) ? @getimagesize($logo) : false;
        }
        return [
            'name'=>(string)Setting::get('site_name','IPRPMS'),
            'abbreviation'=>(string)Setting::get('site_abbv','IPRPMS'),
            'accent'=>$accent,'dark'=>$mix(-.35),'tint'=>$mix(.92),'pale'=>$mix(.97),
            'logo_path'=>$info ? $logo : null,
            'logo_uri'=>$info ? 'data:'.$info['mime'].';base64,'.base64_encode(file_get_contents($logo)) : null,
        ];
    }
}
