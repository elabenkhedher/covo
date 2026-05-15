import os
import json
import requests
import logging

# Import conditionnel de sklearn (mode dégradé si absent)
try:
    from sklearn.linear_model import LinearRegression
    from sklearn.preprocessing import StandardScaler
    import numpy as np
    import pickle
    HAS_ML = True
except ImportError:
    HAS_ML = False
    logging.getLogger(__name__).warning("Mode IA dégradé sans Scikit-Learn.")

logger = logging.getLogger(__name__)

class PriceEstimator:
    # Coûts carburant par type de véhicule
    VEHICLE_COST_PER_KM = {'citadine': 0.08, 'berline': 0.11, 'suv': 0.15, 'utilitaire': 0.18, 'default': 0.11}
    MODEL_PATH, SCALER_PATH = 'data/price_model.pkl', 'data/price_scaler.pkl'

    def __init__(self):
        self.model, self.scaler = None, None
        self._load_or_train()

    # Charge le modèle ML ou initialise le fallback
    def _load_or_train(self):
        if not HAS_ML: return
        if os.path.exists(self.MODEL_PATH):
            with open(self.MODEL_PATH, 'rb') as f: self.model = pickle.load(f)
            with open(self.SCALER_PATH, 'rb') as f: self.scaler = pickle.load(f)

    # Prédit le prix (ML ou formule mathématique)
    def predict(self, distance_km, duration_minutes, nb_passengers, vehicle_type='berline'):
        cout = self.VEHICLE_COST_PER_KM.get(vehicle_type.lower(), self.VEHICLE_COST_PER_KM['default'])
        if HAS_ML and self.model:
            feat = np.array([[distance_km, duration_minutes, nb_passengers, cout]])
            return max(round(float(self.model.predict(self.scaler.transform(feat))[0]), 2), 2.0)
        return max(round(5.0 + (distance_km * cout * 10) + (duration_minutes * 0.05), 2), 2.0)

class MatchingService:
    # Service de matching passagers/conducteurs via Mistral
    API_URL = "https://api.mistral.ai/v1/chat/completions"

    def __init__(self):
        self.api_key = os.environ.get('MISTRAL_API_KEY', '')

    # Analyse les compatibilités via LLM
    def find_best_matches(self, driver, passengers):
        if not self.api_key: return {"matches": [], "summary": "API Key manquante."}
        payload = {
            "model": "mistral-small-latest",
            "messages": [
                {"role": "system", "content": "Expert covoiturage. Réponds en JSON uniquement."},
                {"role": "user", "content": f"Match driver {driver} with {passengers}. Score 0-100."}
            ]
        }
        res = requests.post(self.API_URL, headers={"Authorization": f"Bearer {self.api_key}"}, json=payload)
        return res.json()['choices'][0]['message']['content']

class AssistantService:
    # Assistant de site intelligent (RAG) via Mistral
    API_URL = "https://api.mistral.ai/v1/chat/completions"

    def __init__(self):
        self.api_key = os.environ.get('MISTRAL_API_KEY', '')

    # Répond aux questions sur le site en utilisant le contexte fourni
    def ask(self, question, context):
        if not self.api_key: return {"answer": "IA indisponible."}
        payload = {
            "model": "mistral-small-latest",
            "messages": [
                {"role": "system", "content": "Tu es l'assistant de CovoISET. Réponds en français selon le contexte."},
                {"role": "user", "content": f"CONTEXT: {context}\nQUESTION: {question}"}
            ]
        }
        res = requests.post(self.API_URL, headers={"Authorization": f"Bearer {self.api_key}"}, json=payload)
        return {"answer": res.json()['choices'][0]['message']['content'].strip()}
