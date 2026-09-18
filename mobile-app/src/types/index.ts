// ─── Utilisateur (retourné par GET /api/user) ───
export interface ApiUser {
  id: number | string;
  name?: string;
  nom?: string;
  prenom?: string;
  email: string;
  identifiant_unique?: string;
  matricule?: string;
  role: 'etudiant' | 'admin' | 'super_admin';
  /** Délégué de sa promotion : donne accès à l'onglet « QR du cours ». */
  est_responsable?: boolean;
  etablissement_id?: number | string;
  filiere_id?: string;
  annee_id?: string;
  created_at?: string;
  updated_at?: string;
}

// ─── Réponse POST /api/auth/student/login ───
export interface AuthResponse {
  success: boolean;
  message: string;
  data: {
    user: ApiUser;
    token: string;
  };
}

// ─── Payload POST /api/presence/scan ───
// Le scan est authentifié : la route porte « auth:sanctum » + « ability:etudiant »
// et le serveur lit l'étudiant dans le jeton. « identifiant_unique » et
// « scan_challenge » ont disparu du contrat — le premier laissait scanner au nom
// d'un autre en saisissant son identifiant, le second n'était qu'un aller-retour
// supplémentaire que l'authentification rend inutile.
export interface ScanPayload {
  token: string;
  device_fingerprint: string;
  latitude?: number;
  longitude?: number;
  ssid?: string;
  bssid?: string;
}

// ─── Réponse POST /api/presence/scan ───
export interface ScanResponse {
  success: boolean;
  message: string;
  /**
   * Charge utile renvoyée par POST /presence/scan en cas de succès. Le serveur
   * la place sous `data` (createdResponse) : c'est là que se trouve l'heure
   * effective d'enregistrement, seule source fiable pour l'afficher.
   */
  data?: {
    etudiant?: string;
    matricule?: string;
    /** Format H:i:s, horodaté par le serveur. */
    heure?: string;
    cours?: string;
    verification?: Record<string, unknown>;
  };
  presence?: Presence;
  double_scan_detected?: boolean;
  challenge?: string;
  verification_log?: Record<string, unknown>;
}

// ─── Enregistrement de présence ───
export interface Presence {
  id: number | string;
  etudiant_id: string;
  evenement_id: number | string;
  heure_scan: string;
  device_fingerprint: string | null;
  ip_address: string | null;
  statut: 'valide' | 'rejete' | 'suspect' | 'en_attente' | 'invalide';
  latitude: number | null;
  longitude: number | null;
  validated_by?: number;
  validated_at?: string;
  validation_motif?: string;
  created_at?: string;
  updated_at?: string;
  evenement?: EventInfo;
}

// ─── Événement / Cours (GET /api/presence/course-by-token/{token}) ───
export interface EventInfo {
  id: number | string;
  titre?: string;
  nom?: string;
  description?: string;
  date?: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cours?: {
    id: number | string;
    nom: string;
    code?: string;
  };
  enseignant?: {
    id: number | string;
    nom: string;
    prenom: string;
  };
}

// ─── Pagination générique ───
export interface PaginatedResponse<T> {
  data: T[];
  links?: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}

// ─── Erreur API standard ───
export interface ApiError {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

// ─── Statistiques dashboard ───
export interface ScanStats {
  total_scans: number;
  validated: number;
  rejected: number;
  pending: number;
  taux_presence: number;
}