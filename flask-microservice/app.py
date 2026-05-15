from dotenv import load_dotenv
load_dotenv()

from flask import Flask, request, jsonify
from model_ai import PriceEstimator, MatchingService, AssistantService
import logging

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialisation des services IA
price_estimator = PriceEstimator()
matching_service = MatchingService()
assistant_service = AssistantService()

# Endpoint de santé du service
@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({"status": "ok", "service": "carpool-ai-microservice"}), 200

# Estimation du prix via régression
@app.route('/api/estimate-price', methods=['POST'])
def estimate_price():
    try:
        data = request.get_json()
        if not data: return jsonify({"error": "JSON manquant"}), 400

        res = price_estimator.predict(
            distance_km=float(data['distance_km']),
            duration_minutes=float(data['duration_minutes']),
            nb_passengers=int(data['nb_passengers']),
            vehicle_type=data.get('vehicle_type', 'berline')
        )
        return jsonify({"success": True, "estimated_price_total": res}), 200
    except Exception as e:
        logger.error(f"Price error: {e}")
        return jsonify({"error": "Erreur serveur"}), 500

# Matching intelligent via Mistral IA
@app.route('/api/match-passengers', methods=['POST'])
def match_passengers():
    try:
        data = request.get_json()
        if not data or 'driver' not in data: return jsonify({"error": "Données incomplètes"}), 400

        matches = matching_service.find_best_matches(data['driver'], data['passengers'])
        return jsonify({"success": True, "matches": matches}), 200
    except Exception as e:
        logger.error(f"Match error: {e}")
        return jsonify({"error": "Erreur serveur"}), 500

# Assistant intelligent (RAG) via Mistral IA
@app.route('/api/assistant', methods=['POST'])
def site_assistant():
    try:
        data = request.get_json()
        if not data or 'question' not in data: return jsonify({"error": "Question manquante"}), 400

        result = assistant_service.ask(data['question'], data.get('context', {}))
        return jsonify(result), 200
    except Exception as e:
        logger.error(f"Assistant error: {e}")
        return jsonify({"error": "Erreur serveur"}), 500

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
