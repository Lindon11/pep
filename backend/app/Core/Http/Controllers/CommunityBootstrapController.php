<?php

namespace App\Core\Http\Controllers;

use Illuminate\Http\Request;

class CommunityBootstrapController extends Controller
{
    /**
     * Consolidates multiple API requests into a single response to reduce I/O overhead
     * and prevent proxy timeouts during local development in slow environments (WSL2).
     */
    public function home(Request $request)
    {
        $data = [
            'discussions' => null,
            'lab_results' => null,
            'vendors' => null,
            'announcements' => null,
            'members' => null,
        ];

        // 1. Discussions
        if (class_exists(\App\Plugins\Discussions\Controllers\CommunityDiscussionController::class)) {
            $req = Request::create('/api/v1/community/discussions', 'GET', ['limit' => 5]);
            $req->setUserResolver(fn() => $request->user());
            $response = app(\App\Plugins\Discussions\Controllers\CommunityDiscussionController::class)->index($req);
            $data['discussions'] = $this->serializeResponse($response, $req);
        }

        // 2. Lab Results
        if (class_exists(\App\Plugins\LabResults\Controllers\CommunityLabResultController::class)) {
            $req = Request::create('/api/v1/community/lab-results', 'GET', ['limit' => 5]);
            $req->setUserResolver(fn() => $request->user());
            $response = app(\App\Plugins\LabResults\Controllers\CommunityLabResultController::class)->index($req);
            $data['lab_results'] = $this->serializeResponse($response, $req);
        }

        // 3. Vendors
        if (class_exists(\App\Plugins\Vendors\Controllers\CommunityVendorController::class)) {
            $req = Request::create('/api/v1/community/vendors', 'GET', ['limit' => 5]);
            $req->setUserResolver(fn() => $request->user());
            $response = app(\App\Plugins\Vendors\Controllers\CommunityVendorController::class)->index($req);
            $data['vendors'] = $this->serializeResponse($response, $req);
        }

        // 4. Announcements
        if (class_exists(\App\Plugins\Announcements\Controllers\CommunityAnnouncementController::class)) {
            $req = Request::create('/api/v1/community/announcements', 'GET', ['limit' => 5]);
            $req->setUserResolver(fn() => $request->user());
            $response = app(\App\Plugins\Announcements\Controllers\CommunityAnnouncementController::class)->index($req);
            $data['announcements'] = $this->serializeResponse($response, $req);
        }

        // 5. Members
        if (class_exists(\App\Plugins\Community\Controllers\CommunityMemberController::class)) {
            $req = Request::create('/api/v1/community/members', 'GET', ['limit' => 5]);
            $req->setUserResolver(fn() => $request->user());
            $response = app(\App\Plugins\Community\Controllers\CommunityMemberController::class)->index($req);
            $data['members'] = $this->serializeResponse($response, $req);
        }

        return response()->json($data);
    }

    /**
     * Converts a Controller response (ResourceCollection, JsonResponse, array) into a raw array
     * to prevent serialization issues when embedding it in the parent JSON response.
     */
    private function serializeResponse($response, Request $request)
    {
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response->getData(true);
        }

        if (method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request);
            return json_decode($httpResponse->getContent(), true);
        }

        if (is_array($response)) {
            return $response;
        }

        return json_decode(json_encode($response), true);
    }
}
