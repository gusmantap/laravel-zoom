<?php
namespace Services;

use Error;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\ErrorHandler\Debug;

class ZoomService {
	protected $token;
	protected $api_url;
	protected $jwt;
	protected $default_email;
	protected $api_key;
	protected $api_secret;

	public function __construct()
	{
		$this->api_url = config('zoom.api_url');
		$this->default_email = config('zoom.default_email');
		$this->api_key = config('zoom.api_key');
		$this->api_secret = config('zoom.api_secret');

		$this->generateZoomToken();
	}

	private function getHeaders() {
		$headers = [
            'Authorization' => 'Bearer '.$this->jwt,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
		return $headers;
	}

	public function http($url, $method = 'GET', $data = null) {
		$headers = $this->getHeaders();

		try {
			$body = ['headers' => $headers];

			if($method == 'POST' || $method == 'PATCH' || $method == 'PUT' ) {
				if($data == null) {
					throw new \Exception("Post need body request");
				} else {
					$body['json'] = $data;
				}
			} else if($method == 'GET') {
				if($data) {
					$body['query'] = $data;
				}
			} else if($method == 'DELETE') {
				$body['query'] = [
					'schedule_for_reminder'=>true
				];
			}

			$response = null;

			if($method == 'GET') {
				$response =  (new Client())->get($this->api_url.$url, $body);
			} else if($method == 'POST') {
				$response =  (new Client())->post($this->api_url.$url, $body);
				return $response;
			} else if($method == 'PATCH') {
				$response =  (new Client())->patch($this->api_url.$url, $body);
				return $response;
			} else if($method == 'DELETE'){
				$response =  (new Client())->delete($this->api_url.$url, $body);
				return $response;
			}
			return $response;
		} catch(\Throwable $th) {
			Log::error(['Zoom Failed http '.$this->api_url.$url=>$th->getMessage()]);
			report($th);
			throw $th;
		}
	}

	public function generateZoomToken()
    {

        $payload = [
            'iss' => $this->api_key,
            'exp' => strtotime('+1 minute'),
        ];

        $this->jwt = \Firebase\JWT\JWT::encode($payload, $this->api_secret, 'HS256');
    }

	public function getListMeeting($request) {
		$path = "users/{$this->default_email}/meetings";
		$response = $this->http($path, 'GET', $request->only('total_record', 'page_size', 'page_number'));

        return [
            'success' => $response->getStatusCode() === 200,
            'data'    => json_decode($response->getBody(), true),
        ];
	}

	private function generatePassword() {
		$digits    = array_flip(range('0', '9'));
		$lowercase = array_flip(range('a', 'z'));
		$uppercase = array_flip(range('A', 'Z'));
		$combined  = array_merge($digits, $lowercase, $uppercase);

		$password  = str_shuffle(array_rand($digits) .
								array_rand($lowercase) .
								array_rand($uppercase) .
								implode(array_rand($combined, rand(4, 8))));

		return substr($password, 0, 5);
	}

	public function createMeeting($data) {
		Validator::validate($data, [
			'topic'=>'string|required',
			'agenda'=>'max:2000',
			// 'type'=>'required',
			// 'start_time'=>'datetime',
			'duration'=>'integer',
			'schedule_for'=>'email',
			'alternative_hosts'=>'email',
			'setting'=>[
				'meeting_authentication'=>'required|boolean',
				'join_before_host'=>'required|boolean',
				'host_video'=>'required|boolean',
				'participant_video'=>'required|boolean'
			]
		]);
		$data['password'] = $this->generatePassword();

		$path = "users/{$this->default_email}/meetings";
		$response = $this->http($path, 'POST', $data);

		return [
			'success'=> $response->getStatusCode() === 201,
			'data'=> json_decode($response->getBody(), true),
		];
	}

	public function getListUser() {

		$path = 'users';

		$response = $this->http($path, 'GET');

        return [
            'success' => $response->getStatusCode() === 200,
            'data'    => json_decode($response->getBody(), true),
        ];
	}



	public function findMeeting($meeting_id) {
		$path = "meetings/{$meeting_id }";
		$response = $this->http($path, 'GET');

        return [
            'success' => $response->getStatusCode() === 200,
            'data'    => json_decode($response->getBody(), true),
        ];
	}

	public function updateMeeting($meeting_id, $data) {
		$path = "meetings/{$meeting_id}";

		$response = $this->http($path, 'PATCH', $data);

		return [
			'success'=> $response->getStatusCode() === 204,
			'status_code'=> $response->getStatusCode(),
			'data'=> json_decode($response->getBody(), true),
		];
	}

	public function deleteMeeting($meeting_id) {
		$path = "meetings/{$meeting_id}";
		$response = $this->http($path, "DELETE");

		return [
			'success'=> $response->getStatusCode() === 204,
			'status_code'=> $response->getStatusCode(),
			'data'=> json_decode($response->getBody(), true),
		];
	}
}