"""
train_model.py - Script pour entraîner le modèle de prix sur un vrai dataset CSV.

Usage:
    python train_model.py --data data/trajets.csv

Format CSV attendu (colonnes):
    distance_km, duration_minutes, nb_passengers, vehicle_type, price_total

Exemple:
    distance_km,duration_minutes,nb_passengers,vehicle_type,price_total
    45.5,60,2,berline,12.50
    120.0,95,3,suv,28.00
    ...
"""

import argparse
import os
import pickle
import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_error, r2_score

VEHICLE_COST_MAP = {
    'citadine':   0.08,
    'berline':    0.11,
    'suv':        0.15,
    'utilitaire': 0.18
}

def load_data(csv_path: str) -> pd.DataFrame:
    """Charge et valide le dataset CSV."""
    print(f"[INFO] Chargement de : {csv_path}")
    df = pd.read_csv(csv_path)

    required = ['distance_km', 'duration_minutes', 'nb_passengers', 'vehicle_type', 'price_total']
    missing = [col for col in required if col not in df.columns]
    if missing:
        raise ValueError(f"Colonnes manquantes dans le CSV : {missing}")

    print(f"[INFO] {len(df)} trajets chargés.")
    print(df.describe())
    return df

def prepare_features(df: pd.DataFrame):
    """Transforme le DataFrame en features numériques."""
    df = df.copy()
    df['cout_km'] = df['vehicle_type'].str.lower().map(VEHICLE_COST_MAP).fillna(0.11)

    X = df[['distance_km', 'duration_minutes', 'nb_passengers', 'cout_km']].values
    y = df['price_total'].values
    return X, y

def evaluate_models(X_train, X_test, y_train, y_test, scaler):
    """Compare plusieurs modèles de régression."""
    models = {
        'LinearRegression':      LinearRegression(),
        'RandomForest':          RandomForestRegressor(n_estimators=100, random_state=42),
        'GradientBoosting':      GradientBoostingRegressor(n_estimators=100, random_state=42)
    }

    best_model = None
    best_mae = float('inf')

    print("\n--- Comparaison des modèles ---")
    for name, model in models.items():
        model.fit(scaler.transform(X_train), y_train)
        y_pred = model.predict(scaler.transform(X_test))
        mae = mean_absolute_error(y_test, y_pred)
        r2  = r2_score(y_test, y_pred)
        print(f"  {name:25s} | MAE={mae:.2f}€ | R²={r2:.4f}")

        if mae < best_mae:
            best_mae = mae
            best_model = (name, model)

    print(f"\n[INFO] Meilleur modèle : {best_model[0]} (MAE={best_mae:.2f}€)")
    return best_model[1]

def train(csv_path: str):
    """Pipeline complet d'entraînement."""
    df = load_data(csv_path)
    X, y = prepare_features(df)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )

    scaler = StandardScaler()
    scaler.fit(X_train)

    best_model = evaluate_models(X_train, X_test, y_train, y_test, scaler)

    # Cross-validation
    cv_scores = cross_val_score(
        best_model,
        scaler.transform(X),
        y,
        cv=5,
        scoring='neg_mean_absolute_error'
    )
    print(f"\n[Cross-Val] MAE moyen : {-cv_scores.mean():.2f}€ ± {cv_scores.std():.2f}€")

    # Sauvegarde
    os.makedirs('data', exist_ok=True)
    with open('data/price_model.pkl', 'wb') as f:
        pickle.dump(best_model, f)
    with open('data/price_scaler.pkl', 'wb') as f:
        pickle.dump(scaler, f)

    print("\n[OK] Modèle sauvegardé dans data/price_model.pkl")
    print("[OK] Scaler sauvegardé dans data/price_scaler.pkl")

def generate_sample_data():
    """Génère un fichier CSV d'exemple pour tester."""
    np.random.seed(0)
    n = 200
    vehicle_types = ['citadine', 'berline', 'suv', 'utilitaire']
    cost_map = {'citadine': 0.08, 'berline': 0.11, 'suv': 0.15, 'utilitaire': 0.18}

    data = []
    for _ in range(n):
        dist = np.random.uniform(5, 250)
        dur  = dist * np.random.uniform(0.9, 1.4)
        pass_ = np.random.randint(1, 5)
        vtype = np.random.choice(vehicle_types)
        price = dist * cost_map[vtype] * 8 + np.random.normal(0, 2)
        price = max(price, 2.0)
        data.append([round(dist, 1), round(dur, 0), pass_, vtype, round(price, 2)])

    os.makedirs('data', exist_ok=True)
    df = pd.DataFrame(data, columns=[
        'distance_km', 'duration_minutes', 'nb_passengers', 'vehicle_type', 'price_total'
    ])
    df.to_csv('data/trajets.csv', index=False)
    print("[OK] Fichier data/trajets.csv généré avec", n, "trajets.")
    return 'data/trajets.csv'

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Entraîne le modèle de prix covoiturage")
    parser.add_argument('--data', type=str, help="Chemin vers le fichier CSV")
    parser.add_argument('--generate-sample', action='store_true',
                        help="Génère un CSV d'exemple et entraîne dessus")
    args = parser.parse_args()

    if args.generate_sample:
        csv_path = generate_sample_data()
        train(csv_path)
    elif args.data:
        train(args.data)
    else:
        print("Usage: python train_model.py --data data/trajets.csv")
        print("       python train_model.py --generate-sample")
