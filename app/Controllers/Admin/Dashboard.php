<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Services;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $this->data['title'] = lang('Admin.dashboard');
        $this->data['quote'] = $this->getZenQuote();
        // Display the admin dashboard view
        return view('admin/dashboard', $this->data);
    }

    private function getZenQuote()
    {
        try {
            $client = Services::curlrequest();
            $response = $client->get('https://zenquotes.io/api/random', [
                'verify' => false,
            ]);

            if ($response->getStatusCode() === 200) {
                $quotes = json_decode($response->getBody(), true);
                if (!empty($quotes[0]['q'])) {
                    return '"' . $quotes[0]['q'] . '" - ' . $quotes[0]['a'];
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to fetch ZenQuote: ' . $e->getMessage());
        }

        return null;
    }
}
