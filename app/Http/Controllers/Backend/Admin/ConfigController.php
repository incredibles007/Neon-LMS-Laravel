<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FileUploadTrait;
use App\Locale;
use App\Models\Config;
use App\Models\OauthClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ConfigController extends Controller
{
    use FileUploadTrait;

    public function getGeneralSettings()
    {
        if(!auth()->user()->isAdmin()){
            return abort(403);
        }
        $type = config('theme_layout');
        $sections = Config::where('key', '=', 'layout_' . $type)->first();
        $footer_data = Config::where('key', '=', 'footer_data')->first();
        $footer_data = json_decode($footer_data->value);
        $sections = json_decode($sections->value);
        $app_locales = Locale::get();
        $api_clients = OauthClient::paginate(10);
        return view('backend.settings.general', compact('sections', 'footer_data','app_locales','api_clients'));
    }

    public function saveGeneralSettings(Request $request)
    {
        if (($request->get('mail_provider') == 'sendgrid') && ($request->get('list_selection') == 2)) {
            if ($request->get('list_name') == "") {
                return back()->withErrors(['Please input list name']);
            }
            $apiKey = config('sendgrid_api_key');
            $sg = new \SendGrid($apiKey);
            try {
                $request_body = json_decode('{"name": "' . $request->get('list_name') . '"}');
                $response = $sg->client->contactdb()->lists()->post($request_body);
                if ($response->statusCode() != 201) {
                    return back()->withErrors(['Check name and try again']);
                }
                $response = json_decode($response->body());
                $sendgrid_list_id = Config::where('sendgrid_list_id')->first();
                $sendgrid_list_id->value = $response->id;
                $sendgrid_list_id->save();
            } catch (Exception $e) {
                \Log::info($e->getMessage());
            }

        }
        $requests = $this->saveLogos($request);
        if ($request->get('access_registration') == null) {
            $requests['access_registration'] = 0;
        }
        if (!$request->get('mailchimp_double_opt_in')) {
            $requests['mailchimp_double_opt_in'] = 0;
        }
        if ($request->get('access_users_change_email') == null) {
            $requests['access_users_change_email'] = 0;
        }
        if ($request->get('access_users_confirm_email') == null) {
            $requests['access_users_confirm_email'] = 0;
        }
        if ($request->get('access_captcha_registration') == null) {
            $requests['access_captcha_registration'] = 0;
        }
        if ($request->get('access_users_requires_approval') == null) {
            $requests['access_users_requires_approval'] = 0;
        }
        if ($request->get('services__stripe__active') == null) {
            $requests['services__stripe__active'] = 0;
        }
        if ($request->get('paypal__active') == null) {
            $requests['paypal__active'] = 0;
        }
        if ($request->get('payment_offline_active') == null) {
            $requests['payment_offline_active'] = 0;
        }
        if ($request->get('backup__status') == null) {
            $requests['backup__status'] = 0;
        }
        if ($request->get('access__captcha__registration') == null) {
            $requests['access__captcha__registration'] = 0;
        }
        if ($request->get('retest') == null) {
            $requests['retest'] = 0;
        }
        if ($request->get('lesson_timer') == null) {
            $requests['lesson_timer'] = 0;
        }
        if ($request->get('show_offers') == null) {
            $requests['show_offers'] = 0;
        }
        if ($request->get('onesignal_status') == null) {
            $requests['onesignal_status'] = 0;
        }
        foreach ($requests->all() as $key => $value) {
            if ($key != '_token') {
                $key = str_replace('__', '.', $key);
                $config = Config::firstOrCreate(['key' => $key]);
                $config->value = $value;
                $config->save();

                if($key === 'app.locale'){
                    Locale::where('short_name','!=',$value)->update(['is_default' => 0]);
                    $locale = Locale::where('short_name','=',$value)->first();
                    $locale->is_default = 1;
                    $locale->save();
                }
            }
        }

        return back()->withFlashSuccess(__('alerts.backend.general.updated'));
    }

    public function getSocialSettings()
    {
        if(!auth()->user()->isAdmin()){
            return abort(403);
        }
        return view('backend.settings.social');
    }

    public function saveSocialSettings(Request $request)
    {
        $requests = request()->all();
        if ($request->get('services__facebook__active') == null) {
            $requests['services__facebook__active'] = 0;
        }
        if ($request->get('services__google__active') == null) {
            $requests['services__google__active'] = 0;
        }
        if ($request->get('services__twitter__active') == null) {
            $requests['services__twitter__active'] = 0;
        }
        if ($request->get('services__linkedin__active') == null) {
            $requests['services__linkedin__active'] = 0;
        }
        if ($request->get('services__github__active') == null) {
            $requests['services__github__active'] = 0;
        }
        if ($request->get('services__bitbucket__active') == null) {
            $requests['services__bitbucket__active'] = 0;
        }

        foreach ($requests as $key => $value) {
            if ($key != '_token') {
                $key = str_replace('__', '.', $key);
                $config = Config::firstOrCreate(['key' => $key]);
                $config->value = $value;
                $config->save();


            }
        }

        return back()->withFlashSuccess(__('alerts.backend.general.updated'));
    }

    public function getContact()
    {
        if(!auth()->user()->isAdmin()){
            return abort(403);
        }
        $contact_data = config('contact_data');
        return view('backend.settings.contact', compact('contact_data'));
    }

    public function getFooter()
    {
        if(!auth()->user()->isAdmin()){
            return abort(403);
        }
        $footer_data = config('footer_data');
        return view('backend.settings.footer', compact('footer_data'));
    }

    public function getNewsletterConfig()
    {
        if(!auth()->user()->isAdmin()){
            return abort(403);
        }
        $newsletter_config = config('newsletter_config');
        return view('backend.settings.newsletter', compact('newsletter_config'));
    }

    public function getSendGridLists(Request $request)
    {
        $apiKey = $request->apiKey;
        $sendgrid = Config::firstOrCreate(['key' => 'sendgrid_api_key']);
        $sendgrid->value = $apiKey;
        $sendgrid->save();
        $sg = new \SendGrid($apiKey);
        try {
            $response = $sg->client->contactdb()->lists()->get();
            if ($response->statusCode() != 200) {
                return ['status' => 'error', 'message' => 'Please input valid key'];
            } else {
                return ['status' => 'success', 'body' => $response->body()];
            }
        } catch (\Exception $e) {
            \Log::info($e->getMessage());
            return ['status' => 'error', 'message' => 'Something went wrong. Please check key'];
        }

    }


    public function troubleshoot()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');

        shell_exec('cd '.base_path().'/public');
        shell_exec('rm storage');
        \File::link(storage_path('app/public'), public_path('storage'));
        return back();
    }

}
