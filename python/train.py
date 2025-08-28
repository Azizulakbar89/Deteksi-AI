import os
import sys
import json
import traceback
import numpy as np
import cv2
import shutil
from sklearn.metrics import (
    confusion_matrix,
    accuracy_score,
    precision_score,
    recall_score,
    f1_score,
)

sys.stderr = sys.stdout

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    import tensorflow as tf
    from tensorflow.keras.applications import Xception
    from tensorflow.keras.models import Model, save_model, load_model
    from tensorflow.keras.layers import Dense, GlobalAveragePooling2D, Dropout
    from tensorflow.keras.optimizers import Adam
    from tensorflow.keras.callbacks import EarlyStopping, ReduceLROnPlateau
    import mysql.connector
except ImportError as e:
    print(json.dumps({"error": f"Import error: {e}"}))
    sys.exit(1)

IMG_SIZE = (299, 299)
BATCH_SIZE = 8
EPOCHS = 5

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "testestes",
}


def setup_database():
    try:
        conn = mysql.connector.connect(
            host=DB_CONFIG["host"],
            user=DB_CONFIG["user"],
            password=DB_CONFIG["password"],
            database=DB_CONFIG["database"],
            autocommit=True,
        )
        cursor = conn.cursor()
        print("Database connection established", file=sys.stderr)
        return conn, cursor
    except Exception as e:
        print(f"Database connection error: {e}", file=sys.stderr)
        return None, None


def save_preprocessed_image(img, split_ratio, img_type, filename):
    try:
        base_dir = os.path.abspath(
            os.path.join(os.path.dirname(__file__), "../storage/images")
        )
        save_dir = os.path.join(base_dir, str(split_ratio), img_type)
        os.makedirs(save_dir, exist_ok=True)

        save_path = os.path.join(save_dir, filename)
        img_to_save = (img * 255).astype(np.uint8)
        cv2.imwrite(save_path, img_to_save)

        return save_path
    except Exception as e:
        print(f"Error saving preprocessed image: {e}", file=sys.stderr)
        return None


def resize_image(image_path, split_ratio, img_type, filename):
    try:
        img = cv2.imread(image_path)
        if img is None:
            print(f"Failed to read image with cv2: {image_path}", file=sys.stderr)
            return None

        original_height, original_width = img.shape[:2]

        if original_height == 32 and original_width == 32:
            print(
                f"Upsampling image from 32x32 to 299x299: {image_path}", file=sys.stderr
            )

            img = cv2.resize(img, IMG_SIZE, interpolation=cv2.INTER_LINEAR)
            img = img / 255.0

            save_preprocessed_image(img, split_ratio, img_type, filename)

        else:
            img = cv2.resize(img, IMG_SIZE, interpolation=cv2.INTER_LINEAR)
            img = img / 255.0

            save_preprocessed_image(img, split_ratio, img_type, filename)

        return img

    except Exception as e:
        print(f"Error processing image {image_path}: {e}", file=sys.stderr)
        return None


def calculate_bilinear_interpolation_example():
    pixels = np.array([[50, 70], [30, 90]], dtype=np.float32)

    x, y = 0.5, 0.5

    result = (
        (1 - x) * (1 - y) * pixels[0, 0]
        + x * (1 - y) * pixels[0, 1]
        + (1 - x) * y * pixels[1, 0]
        + x * y * pixels[1, 1]
    )

    print(f"Contoh perhitungan interpolasi bilinear:", file=sys.stderr)
    print(f"Matriks input 2x2:\n{pixels}", file=sys.stderr)
    print(f"Hasil interpolasi di (0.5, 0.5): {result}", file=sys.stderr)

    return result


def load_data(split_ratio):
    conn, cursor = setup_database()
    if not conn:
        return None, None, None, None, None, None

    try:
        STORAGE_PATH = os.path.abspath(
            os.path.join(os.path.dirname(__file__), "../storage/app/public")
        )
        print(f"Storage path: {STORAGE_PATH}", file=sys.stderr)

        interpolation_example = calculate_bilinear_interpolation_example()

        cursor.execute(
            "SELECT filename, path, type FROM images WHERE split = 'train' AND split_ratio = %s",
            (split_ratio,),
        )
        train_rows = cursor.fetchall()

        cursor.execute(
            "SELECT filename, path, type FROM images WHERE split = 'test' AND split_ratio = %s",
            (split_ratio,),
        )
        test_rows = cursor.fetchall()

        X_train, y_train, train_filenames = [], [], []
        X_test, y_test, test_filenames, test_types = [], [], [], []

        print(f"Processing {len(train_rows)} training images...", file=sys.stderr)
        for i, (filename, path, typ) in enumerate(train_rows):
            full_path = os.path.join(STORAGE_PATH, path)
            if os.path.exists(full_path):
                img = resize_image(full_path, split_ratio, typ, filename)
                if img is not None:
                    X_train.append(img)
                    label = 0 if typ == "real" else 1
                    y_train.append(label)
                    train_filenames.append(filename)

                    if (i + 1) % 10 == 0:
                        print(
                            f"Processed {i + 1}/{len(train_rows)} training images",
                            file=sys.stderr,
                        )

        print(f"Processing {len(test_rows)} test images...", file=sys.stderr)
        for i, (filename, path, typ) in enumerate(test_rows):
            full_path = os.path.join(STORAGE_PATH, path)
            if os.path.exists(full_path):
                img = resize_image(full_path, split_ratio, typ, filename)
                if img is not None:
                    X_test.append(img)
                    label = 0 if typ == "real" else 1
                    y_test.append(label)
                    test_filenames.append(filename)
                    test_types.append(typ)

                    if (i + 1) % 10 == 0:
                        print(
                            f"Processed {i + 1}/{len(test_rows)} test images",
                            file=sys.stderr,
                        )

        if len(X_train) == 0 or len(X_test) == 0:
            raise ValueError(
                f"No images loaded from database for split ratio {split_ratio}"
            )

        print(
            f"Loaded {len(X_train)} train and {len(X_test)} test images for split ratio {split_ratio}",
            file=sys.stderr,
        )

        print(f"Upsampling scale factor: {299/32:.2f}x", file=sys.stderr)
        print(f"Interpolation method: INTER_LINEAR (Bilinear)", file=sys.stderr)

        return (
            np.array(X_train),
            np.array(X_test),
            np.array(y_train),
            np.array(y_test),
            test_filenames,
            test_types,
        )

    except Exception as e:
        print(f"Error loading data: {e}", file=sys.stderr)
        return None, None, None, None, None, None
    finally:
        if conn:
            cursor.close()
            conn.close()


def build_model():
    base_model = Xception(
        weights="imagenet", include_top=False, input_shape=(299, 299, 3)
    )
    base_model.trainable = False

    x = base_model.output
    x = GlobalAveragePooling2D()(x)
    x = Dense(64, activation="relu")(x)
    x = Dropout(0.5)(x)
    x = Dense(32, activation="relu")(x)
    x = Dropout(0.3)(x)
    x = Dense(1, activation="sigmoid")(x)

    model = Model(inputs=base_model.input, outputs=x)
    model.compile(
        optimizer=Adam(learning_rate=0.0001),
        loss="binary_crossentropy",
        metrics=["accuracy"],
    )
    return model


def train_model(split_percentage):
    try:
        print(f"Loading data for split ratio: {split_percentage}...", file=sys.stderr)
        X_train, X_test, y_train, y_test, test_filenames, test_types = load_data(
            split_percentage
        )

        if X_train is None:
            return {
                "error": f"Failed to load data from database for split ratio {split_percentage}"
            }

        print("Building model...", file=sys.stderr)
        model = build_model()

        print("Training model...", file=sys.stderr)

        history = model.fit(
            X_train,
            y_train,
            epochs=EPOCHS,
            batch_size=BATCH_SIZE,
            validation_data=(X_test, y_test),
            verbose=0,
        )

        print("Making predictions...", file=sys.stderr)
        y_pred_proba = model.predict(X_test, verbose=0)
        y_pred = (y_pred_proba > 0.5).astype("int32").flatten()

        accuracy = accuracy_score(y_test, y_pred)
        precision = precision_score(y_test, y_pred, zero_division=0)
        recall = recall_score(y_test, y_pred, zero_division=0)
        f1 = f1_score(y_test, y_pred, zero_division=0)
        cm = confusion_matrix(y_test, y_pred)

        predictions = []
        for i in range(len(test_filenames)):
            pred_label = "real" if y_pred[i] == 0 else "fake"
            confidence = float(y_pred_proba[i][0])

            predictions.append(
                {
                    "filename": test_filenames[i],
                    "prediction": pred_label,
                    "confidence": confidence,
                }
            )

        model_dir = os.path.join(os.path.dirname(__file__), "model")
        os.makedirs(model_dir, exist_ok=True)
        model_path = os.path.join(model_dir, f"xception_model_{split_percentage}.h5")
        save_model(model, model_path)
        print(f"Model saved to {model_path}", file=sys.stderr)

        results = {
            "accuracy": float(accuracy),
            "precision": float(precision),
            "recall": float(recall),
            "f1_score": float(f1),
            "confusion_matrix": cm.tolist(),
            "split_ratio": split_percentage,
            "predictions": predictions,
            "preprocessing_info": {
                "method": "bilinear_interpolation",
                "input_size": "32x32 (AI generated)",
                "output_size": "299x299",
                "scale_factor": 299 / 32,
                "interpolation_type": "INTER_LINEAR",
            },
        }

        print("Training completed successfully!", file=sys.stderr)
        return results

    except Exception as e:
        error_msg = f"Error in train_model: {str(e)}\n{traceback.format_exc()}"
        print(error_msg, file=sys.stderr)
        return {"error": error_msg}


if __name__ == "__main__":
    try:
        if len(sys.argv) < 2:
            split_percentage = 80
        else:
            split_percentage = int(sys.argv[1])

        print(f"Starting training with split: {split_percentage}%", file=sys.stderr)
        results = train_model(split_percentage)

        print(json.dumps(results))

    except Exception as e:
        error_msg = f"Error in main: {str(e)}\n{traceback.format_exc()}"
        print(error_msg, file=sys.stderr)
        print(json.dumps({"error": error_msg}))
