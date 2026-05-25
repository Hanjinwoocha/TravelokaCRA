<?php
/**
 * Firebase REST API wrapper for Traveloka.
 *
 * Uses:
 *   - Firestore REST API  (server-side CRUD, bypasses security rules via service account)
 *   - Firebase Auth REST API (sign-in / sign-up via web API key)
 *
 * Place your service-account JSON at: Traveloka/firebase-service-account.json
 * Configure PROJECT_ID, API_KEY in includes/firebase_config.php
 */

require_once __DIR__ . '/firebase_config.php';

class Firebase
{
    private string $projectId;
    private string $apiKey;
    private string $serviceAccountPath;
    private string $firestoreBase;
    private string $authBase = 'https://identitytoolkit.googleapis.com/v1';

    public function __construct()
    {
        $this->projectId         = FIREBASE_PROJECT_ID;
        $this->apiKey            = FIREBASE_API_KEY;
        $this->serviceAccountPath = FIREBASE_SERVICE_ACCOUNT;
        $this->firestoreBase     = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OAuth2 Access Token (service account → JWT → Google token endpoint)
    // ─────────────────────────────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        $cacheDir  = __DIR__ . '/../.cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0700, true); }
        $cacheFile = $cacheDir . '/fb_tok_' . md5($this->projectId) . '.json';

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && ($cached['expiry'] ?? 0) > time() + 60) {
                return $cached['token'];
            }
        }

        if (!file_exists($this->serviceAccountPath)) {
            throw new RuntimeException(
                'Firebase service account not found at: ' . $this->serviceAccountPath .
                ' — follow the setup instructions in firebase_config.php'
            );
        }

        $sa  = json_decode(file_get_contents($this->serviceAccountPath), true);
        $now = time();

        $header  = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->b64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/firebase https://www.googleapis.com/auth/cloud-platform',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        openssl_sign("$header.$payload", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = "$header.$payload." . $this->b64url($sig);

        $res = $this->rawPost('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        if (empty($res['access_token'])) {
            throw new RuntimeException('Failed to get Firebase access token: ' . json_encode($res));
        }

        @file_put_contents($cacheFile, json_encode([
            'token'  => $res['access_token'],
            'expiry' => $now + (int)($res['expires_in'] ?? 3600),
        ]));

        return $res['access_token'];
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Low-level HTTP helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function rawPost(string $url, $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }

    private function req(string $method, string $url, ?array $body = null): array
    {
        $token   = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 15,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($ch);
        $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error on $method $url: $err");
        }
        curl_close($ch);

        $data = json_decode($result, true) ?? [];
        if ($code >= 400 && isset($data['error'])) {
            error_log("Firestore $method $url → $code: " . json_encode($data['error']));
        }
        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firestore value encoding/decoding
    // ─────────────────────────────────────────────────────────────────────────

    private function encodeValue($v): array
    {
        if ($v === null)       return ['nullValue'    => null];
        if (is_bool($v))       return ['booleanValue' => $v];
        if (is_int($v))        return ['integerValue'  => (string)$v];
        if (is_float($v))      return ['doubleValue'   => $v];
        if (is_string($v))     return ['stringValue'   => $v];
        if (is_array($v)) {
            if (empty($v) || array_is_list($v)) {
                return ['arrayValue' => ['values' => array_map([$this, 'encodeValue'], $v)]];
            }
            $fields = [];
            foreach ($v as $k => $val) {
                $fields[$k] = $this->encodeValue($val);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['stringValue' => (string)$v];
    }

    private function decodeValue(array $v)
    {
        if (array_key_exists('stringValue',    $v)) return $v['stringValue'];
        if (array_key_exists('integerValue',   $v)) return (int)$v['integerValue'];
        if (array_key_exists('doubleValue',    $v)) return (float)$v['doubleValue'];
        if (array_key_exists('booleanValue',   $v)) return (bool)$v['booleanValue'];
        if (array_key_exists('nullValue',      $v)) return null;
        if (array_key_exists('timestampValue', $v)) return $v['timestampValue'];
        if (array_key_exists('arrayValue',     $v)) {
            return array_map([$this, 'decodeValue'], $v['arrayValue']['values'] ?? []);
        }
        if (array_key_exists('mapValue', $v)) {
            $map = [];
            foreach ($v['mapValue']['fields'] ?? [] as $k => $fv) {
                $map[$k] = $this->decodeValue($fv);
            }
            return $map;
        }
        return null;
    }

    private function encode(array $data): array
    {
        $fields = [];
        foreach ($data as $k => $val) {
            $fields[$k] = $this->encodeValue($val);
        }
        return ['fields' => $fields];
    }

    public function decode(array $doc): array
    {
        $result       = [];
        $result['id'] = basename($doc['name'] ?? '');
        foreach ($doc['fields'] ?? [] as $k => $v) {
            $result[$k] = $this->decodeValue($v);
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firestore CRUD
    // ─────────────────────────────────────────────────────────────────────────

    /** Get one document; returns null if not found. */
    public function getDoc(string $col, string $id): ?array
    {
        $data = $this->req('GET', "{$this->firestoreBase}/{$col}/{$id}");
        if (isset($data['error'])) return null;
        return $this->decode($data);
    }

    /** List all documents in a collection (up to $pageSize). */
    public function listDocs(string $col, int $pageSize = 1000): array
    {
        $data = $this->req('GET', "{$this->firestoreBase}/{$col}?pageSize={$pageSize}");
        return array_map([$this, 'decode'], $data['documents'] ?? []);
    }

    /** Create or overwrite a document with a specific ID. */
    public function setDoc(string $col, string $id, array $data): ?array
    {
        $res = $this->req('PATCH', "{$this->firestoreBase}/{$col}/{$id}", $this->encode($data));
        if (isset($res['error'])) return null;
        return $this->decode($res);
    }

    /** Add a new document (auto-generated ID). Returns the new document ID. */
    public function addDoc(string $col, array $data): ?string
    {
        $res = $this->req('POST', "{$this->firestoreBase}/{$col}", $this->encode($data));
        if (isset($res['error'])) return null;
        return basename($res['name'] ?? '');
    }

    /** Partial update: only the provided fields are overwritten. */
    public function updateDoc(string $col, string $id, array $data): bool
    {
        $mask = implode('&', array_map(
            fn($f) => 'updateMask.fieldPaths=' . urlencode($f),
            array_keys($data)
        ));
        $url = "{$this->firestoreBase}/{$col}/{$id}?{$mask}";
        $res = $this->req('PATCH', $url, $this->encode($data));
        return !isset($res['error']);
    }

    /** Delete a document. */
    public function deleteDoc(string $col, string $id): bool
    {
        $res = $this->req('DELETE', "{$this->firestoreBase}/{$col}/{$id}");
        return !isset($res['error']);
    }

    /**
     * Structured query (runQuery).
     *
     * $filters  = [['field'=>'isActive','op'=>'EQUAL','value'=>true], ...]
     * $orderBy  = ['field'=>'createdAt', 'dir'=>'DESCENDING']
     * ops: EQUAL, NOT_EQUAL, GREATER_THAN, GREATER_THAN_OR_EQUAL,
     *      LESS_THAN, LESS_THAN_OR_EQUAL, ARRAY_CONTAINS, IN, NOT_IN
     *
     * NOTE: filtering on one field + ordering by another requires a
     * composite index in the Firebase console.
     */
    public function query(
        string $col,
        array  $filters  = [],
        array  $orderBy  = [],
        int    $limit    = 1000
    ): array {
        $url   = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:runQuery";

        $sq = ['from' => [['collectionId' => $col]]];

        if (!empty($filters)) {
            $filterObjs = array_map(fn($f) => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $f['field']],
                    'op'    => $f['op'],
                    'value' => $this->encodeValue($f['value']),
                ],
            ], $filters);

            $sq['where'] = count($filterObjs) === 1
                ? $filterObjs[0]
                : ['compositeFilter' => ['op' => 'AND', 'filters' => $filterObjs]];
        }

        if (!empty($orderBy)) {
            $sq['orderBy'] = [['field' => ['fieldPath' => $orderBy['field']], 'direction' => $orderBy['dir'] ?? 'ASCENDING']];
        }

        if ($limit > 0) {
            $sq['limit'] = $limit;
        }

        $res  = $this->req('POST', $url, ['structuredQuery' => $sq]);
        $docs = [];
        foreach ($res as $item) {
            if (isset($item['document'])) {
                $docs[] = $this->decode($item['document']);
            }
        }
        return $docs;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firebase Auth REST API  (uses web API key, NOT service account)
    // ─────────────────────────────────────────────────────────────────────────

    /** Sign in with email + password. Returns ['localId', 'idToken', ...] or ['error'=>...]. */
    public function signIn(string $email, string $password): array
    {
        return $this->rawPost(
            "{$this->authBase}/accounts:signInWithPassword?key={$this->apiKey}",
            json_encode(['email' => $email, 'password' => $password, 'returnSecureToken' => true]),
            ['Content-Type: application/json']
        );
    }

    /** Register a new user. Returns ['localId', 'idToken', ...] or ['error'=>...]. */
    public function signUp(string $email, string $password, string $displayName = ''): array
    {
        return $this->rawPost(
            "{$this->authBase}/accounts:signUp?key={$this->apiKey}",
            json_encode(['email' => $email, 'password' => $password, 'displayName' => $displayName, 'returnSecureToken' => true]),
            ['Content-Type: application/json']
        );
    }

    /**
     * Create a user via Admin API (service account).
     * Used for provider registration (so we can also set custom claims later).
     */
    public function adminCreateUser(string $email, string $password, string $displayName = ''): array
    {
        $token = $this->getAccessToken();
        return $this->rawPost(
            "https://identitytoolkit.googleapis.com/v1/projects/{$this->projectId}/accounts",
            json_encode(['email' => $email, 'password' => $password, 'displayName' => $displayName]),
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
        );
    }

    /** Update a Firebase Auth user's password via the Admin API. Returns true on success. */
    public function updatePassword(string $uid, string $newPassword)
    {
        $token  = $this->getAccessToken();
        $result = $this->rawPost(
            "https://identitytoolkit.googleapis.com/v1/projects/{$this->projectId}/accounts:update",
            json_encode(['localId' => $uid, 'password' => $newPassword]),
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
        );
        return isset($result['localId']) ? true : ($result['error']['message'] ?? 'Unknown error');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────────

    /** Generate a 20-char Firestore-style random ID. */
    public function newId(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $id    = '';
        for ($i = 0; $i < 20; $i++) {
            $id .= $chars[random_int(0, 61)];
        }
        return $id;
    }

    /** Current Unix timestamp in milliseconds (matches mobile createdAt). */
    public static function nowMs(): int
    {
        return (int)(microtime(true) * 1000);
    }

    /** Convert a Y-m-d date string to Unix milliseconds. */
    public static function dateToMs(string $date): int
    {
        return strtotime($date) * 1000;
    }

    /** Convert Unix milliseconds to Y-m-d date string. */
    public static function msToDate(int $ms): string
    {
        return date('Y-m-d', intdiv($ms, 1000));
    }

    /** Booking status label for web display (maps mobile → web-friendly). */
    public static function statusLabel(string $status): string
    {
        return match($status) {
            'Upcoming'  => 'Upcoming',
            'Ongoing'   => 'Ongoing',
            'Completed' => 'Completed',
            'Cancelled' => 'Cancelled',
            default     => $status,
        };
    }

    /** CSS badge class for booking status. */
    public static function statusBadge(string $status): string
    {
        return match($status) {
            'Ongoing'   => 'badge-active',
            'Completed' => 'badge-complete',
            'Cancelled' => 'badge-cancel',
            default     => 'badge-pending', // Upcoming or unknown
        };
    }
}

// Global singleton
function fb(): Firebase
{
    static $instance = null;
    if ($instance === null) {
        $instance = new Firebase();
    }
    return $instance;
}
