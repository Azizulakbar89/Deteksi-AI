# Import library yang diperlukan
import os  # Untuk operasi sistem dan manipulasi path
import sys  # Untuk mengakses argumen command line dan fungsi sistem
import json  # Untuk encoding dan decoding data JSON
import traceback  # Untuk mencetak traceback error yang detail
import numpy as np  # Untuk operasi array dan matematika
import cv2  # OpenCV untuk processing gambar
import shutil  # Untuk operasi file dan direktori
import glob  # Untuk pencarian file dengan pattern tertentu
from sklearn.metrics import (  # Import metrik evaluasi dari scikit-learn
    confusion_matrix,
    accuracy_score,
    precision_score,
    recall_score,
    f1_score,
    roc_curve,
    auc
)

# Redirect stderr ke stdout untuk memudahkan debugging
sys.stderr = sys.stdout

# Tambahkan direktori saat ini ke path Python
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    # Import library TensorFlow/Keras untuk deep learning
    import tensorflow as tf
    from tensorflow.keras.applications import Xception  # Arsitektur model pre-trained
    from tensorflow.keras.models import Model, save_model, load_model  # Fungsi model
    from tensorflow.keras.layers import Dense, GlobalAveragePooling2D, Dropout  # Layer neural network
    from tensorflow.keras.optimizers import Adam  # Optimizer untuk training
    from tensorflow.keras.callbacks import EarlyStopping, ReduceLROnPlateau  # Callbacks untuk training
    import mysql.connector  # Connector untuk database MySQL
except ImportError as e:
    # Jika ada error import, cetak error dan exit
    print(json.dumps({"error": f"Import error: {e}"}))
    sys.exit(1)

# Konstanta untuk ukuran gambar, batch size, dan epochs
IMG_SIZE = (299, 299)  # Ukuran input yang diperlukan model Xception
BATCH_SIZE = 8  # Jumlah sample per batch selama training
EPOCHS = 5  # Jumlah epoch untuk training

# Konfigurasi koneksi database MySQL
DB_CONFIG = {
    "host": "localhost",  # Host database
    "user": "root",  # Username database
    "password": "",  # Password database (kosong dalam contoh ini)
    "database": "testestes",  # Nama database
}


def setup_database():
    """
    Membuat koneksi ke database MySQL dengan konfigurasi yang telah ditentukan.
    Returns:
        conn: Objek koneksi database
        cursor: Objek cursor untuk eksekusi query
    """
    try:
        # Membuat koneksi ke database
        conn = mysql.connector.connect(
            host=DB_CONFIG["host"],
            user=DB_CONFIG["user"],
            password=DB_CONFIG["password"],
            database=DB_CONFIG["database"],
            autocommit=True,  # Auto-commit perubahan
        )
        cursor = conn.cursor()  # Membuat cursor untuk eksekusi query
        print("Database connection established", file=sys.stderr)
        return conn, cursor
    except Exception as e:
        # Jika gagal koneksi, cetak error
        print(f"Database connection error: {e}", file=sys.stderr)
        return None, None


def save_preprocessed_image(img, split_ratio, img_type, filename):
    """
    Menyimpan gambar yang telah diproses ke direktori storage.
    
    Args:
        img: Gambar yang telah diproses (numpy array)
        split_ratio: Rasio split data (training/testing)
        img_type: Tipe gambar ('real' atau 'fake')
        filename: Nama file gambar
    
    Returns:
        save_path: Path lengkap dimana gambar disimpan
    """
    try:
        # Menentukan direktori base untuk penyimpanan
        base_dir = os.path.abspath(
            os.path.join(os.path.dirname(__file__), "../storage/images")
        )
        # Membuat path direktori berdasarkan split ratio dan tipe gambar
        save_dir = os.path.join(base_dir, str(split_ratio), img_type)
        os.makedirs(save_dir, exist_ok=True)  # Membuat direktori jika belum ada

        # Menentukan path lengkap untuk menyimpan gambar
        save_path = os.path.join(save_dir, filename)
        # Konversi nilai pixel dari [0,1] ke [0,255] dan ubah ke uint8
        img_to_save = (img * 255).astype(np.uint8)
        # Simpan gambar menggunakan OpenCV
        cv2.imwrite(save_path, img_to_save)

        return save_path
    except Exception as e:
        # Jika gagal menyimpan, cetak error
        print(f"Error saving preprocessed image: {e}", file=sys.stderr)
        return None


def resize_image(image_path, split_ratio, img_type, filename):
    """
    Memproses gambar: membaca, resize, normalisasi, dan menyimpan.
    
    Args:
        image_path: Path lengkap ke gambar
        split_ratio: Rasio split data
        img_type: Tipe gambar ('real' atau 'fake')
        filename: Nama file gambar
    
    Returns:
        img: Gambar yang telah diproses (numpy array)
    """
    try:
        # Membaca gambar menggunakan OpenCV
        img = cv2.imread(image_path)
        if img is None:
            # Jika gagal membaca gambar
            print(f"Failed to read image with cv2: {image_path}", file=sys.stderr)
            return None

        # Mendapatkan dimensi gambar asli
        original_height, original_width = img.shape[:2]

        # Cek jika gambar berukuran 32x32 (kemungkinan AI generated)
        if original_height == 32 and original_width == 32:
            print(
                f"Upsampling image from 32x32 to 299x299: {image_path}", file=sys.stderr
            )

            # Resize gambar ke 299x299 menggunakan interpolasi bilinear
            img = cv2.resize(img, IMG_SIZE, interpolation=cv2.INTER_LINEAR)
            # Normalisasi nilai pixel ke range [0, 1]
            img = img / 255.0

            # Simpan gambar yang telah diproses
            save_preprocessed_image(img, split_ratio, img_type, filename)

        else:
            # Untuk gambar dengan ukuran selain 32x32
            img = cv2.resize(img, IMG_SIZE, interpolation=cv2.INTER_LINEAR)
            img = img / 255.0

            # Simpan gambar yang telah diproses
            save_preprocessed_image(img, split_ratio, img_type, filename)

        return img

    except Exception as e:
        # Jika error saat processing gambar
        print(f"Error processing image {image_path}: {e}", file=sys.stderr)
        return None


def calculate_bilinear_interpolation_example():
    """
    Menghitung contoh interpolasi bilinear untuk demonstrasi.
    Menunjukkan metode upsampling yang digunakan pada gambar 32x32.
    
    Returns:
        result: Hasil perhitungan interpolasi
    """
    # Matriks contoh 2x2 pixel
    pixels = np.array([[50, 70], [30, 90]], dtype=np.float32)

    # Koordinat titik yang akan diinterpolasi (tengah-tengah)
    x, y = 0.5, 0.5

    # Rumus interpolasi bilinear
    result = (
        (1 - x) * (1 - y) * pixels[0, 0]
        + x * (1 - y) * pixels[0, 1]
        + (1 - x) * y * pixels[1, 0]
        + x * y * pixels[1, 1]
    )

    # Cetak informasi demonstrasi
    print(f"Contoh perhitungan interpolasi bilinear:", file=sys.stderr)
    print(f"Matriks input 2x2:\n{pixels}", file=sys.stderr)
    print(f"Hasil interpolasi di (0.5, 0.5): {result}", file=sys.stderr)

    return result


def load_data(split_ratio):
    """
    Memuat data gambar dari database dan memprosesnya.
    
    Args:
        split_ratio: Rasio split data yang akan dimuat
    
    Returns:
        Tuple berisi data training, testing, labels, dan metadata
    """
    # Setup koneksi database
    conn, cursor = setup_database()
    if not conn:
        return None, None, None, None, None, None

    try:
        # Path ke storage gambar
        STORAGE_PATH = os.path.abspath(
            os.path.join(os.path.dirname(__file__), "../storage/app/public")
        )
        print(f"Storage path: {STORAGE_PATH}", file=sys.stderr)

        # Hitung contoh interpolasi untuk demonstrasi
        interpolation_example = calculate_bilinear_interpolation_example()

        # Query database untuk mendapatkan data training
        cursor.execute(
            "SELECT filename, path, type FROM images WHERE split = 'train' AND split_ratio = %s",
            (split_ratio,),
        )
        train_rows = cursor.fetchall()

        # Query database untuk mendapatkan data testing
        cursor.execute(
            "SELECT filename, path, type FROM images WHERE split = 'test' AND split_ratio = %s",
            (split_ratio,),
        )
        test_rows = cursor.fetchall()

        # Inisialisasi list untuk data training dan testing
        X_train, y_train, train_filenames = [], [], []
        X_test, y_test, test_filenames, test_types = [], [], [], []

        # Proses gambar training
        print(f"Processing {len(train_rows)} training images...", file=sys.stderr)
        for i, (filename, path, typ) in enumerate(train_rows):
            full_path = os.path.join(STORAGE_PATH, path)
            if os.path.exists(full_path):
                # Process dan resize gambar
                img = resize_image(full_path, split_ratio, typ, filename)
                if img is not None:
                    X_train.append(img)  # Tambahkan gambar ke data training
                    # Beri label: 0 untuk real, 1 untuk fake
                    label = 0 if typ == "real" else 1
                    y_train.append(label)  # Tambahkan label
                    train_filenames.append(filename)  # Simpan nama file

                    # Cetak progress setiap 10 gambar
                    if (i + 1) % 10 == 0:
                        print(
                            f"Processed {i + 1}/{len(train_rows)} training images",
                            file=sys.stderr,
                        )

        # Proses gambar testing
        print(f"Processing {len(test_rows)} test images...", file=sys.stderr)
        for i, (filename, path, typ) in enumerate(test_rows):
            full_path = os.path.join(STORAGE_PATH, path)
            if os.path.exists(full_path):
                # Process dan resize gambar
                img = resize_image(full_path, split_ratio, typ, filename)
                if img is not None:
                    X_test.append(img)  # Tambahkan gambar ke data testing
                    # Beri label: 0 untuk real, 1 untuk fake
                    label = 0 if typ == "real" else 1
                    y_test.append(label)  # Tambahkan label
                    test_filenames.append(filename)  # Simpan nama file
                    test_types.append(typ)  # Simpan tipe gambar

                    # Cetak progress setiap 10 gambar
                    if (i + 1) % 10 == 0:
                        print(
                            f"Processed {i + 1}/{len(test_rows)} test images",
                            file=sys.stderr,
                        )

        # Validasi bahwa data berhasil dimuat
        if len(X_train) == 0 or len(X_test) == 0:
            raise ValueError(
                f"No images loaded from database for split ratio {split_ratio}"
            )

        # Cetak informasi tentang data yang dimuat
        print(
            f"Loaded {len(X_train)} train and {len(X_test)} test images for split ratio {split_ratio}",
            file=sys.stderr,
        )

        # Cetak informasi tentang upsampling yang dilakukan
        print(f"Upsampling scale factor: {299/32:.2f}x", file=sys.stderr)
        print(f"Interpolation method: INTER_LINEAR (Bilinear)", file=sys.stderr)

        # Return data dalam format numpy array
        return (
            np.array(X_train),
            np.array(X_test),
            np.array(y_train),
            np.array(y_test),
            test_filenames,
            test_types,
        )

    except Exception as e:
        # Jika error saat loading data
        print(f"Error loading data: {e}", file=sys.stderr)
        return None, None, None, None, None, None
    finally:
        # Pastikan koneksi database ditutup
        if conn:
            cursor.close()
            conn.close()


def build_model():
    """
    Membangun model Xception untuk klasifikasi gambar real/fake.
    
    Returns:
        model: Model Xception yang telah dikompilasi
    """
    # Gunakan Xception pre-trained dengan weights ImageNet
    base_model = Xception(
        weights="imagenet",  # Weight pre-trained
        include_top=False,  # Tidak include top layer (fully connected)
        input_shape=(299, 299, 3)  # Shape input yang diharapkan
    )
    base_model.trainable = False  # Freeze base model (tidak di-training)

    # Tambahkan custom layers di atas base model
    x = base_model.output
    x = GlobalAveragePooling2D()(x)  # Global average pooling
    x = Dense(64, activation="relu")(x)  # Fully connected layer dengan 64 neuron
    x = Dropout(0.5)(x)  # Dropout untuk mengurangi overfitting
    x = Dense(32, activation="relu")(x)  # Fully connected layer dengan 32 neuron
    x = Dropout(0.3)(x)  # Dropout lagi
    x = Dense(1, activation="sigmoid")(x)  # Output layer dengan aktivasi sigmoid (binary classification)

    # Bangun model lengkap
    model = Model(inputs=base_model.input, outputs=x)
    # Kompilasi model dengan optimizer Adam dan binary crossentropy loss
    model.compile(
        optimizer=Adam(learning_rate=0.0001),
        loss="binary_crossentropy",
        metrics=["accuracy"],
    )
    return model


def delete_old_models(split_percentage):
    """
    Menghapus model lama sebelum training dimulai untuk menghemat storage.
    
    Args:
        split_percentage: Rasio split untuk mengidentifikasi model yang akan dihapus
    """
    try:
        model_dir = os.path.join(os.path.dirname(__file__), "model")
        if os.path.exists(model_dir):
            # Cari file model dengan pattern tertentu
            model_pattern = os.path.join(model_dir, f"xception_model_{split_percentage}*")
            model_files = glob.glob(model_pattern)
            
            # Hapus setiap file model yang ditemukan
            for model_file in model_files:
                try:
                    os.remove(model_file)
                    print(f"Deleted old model: {model_file}", file=sys.stderr)
                except Exception as e:
                    print(f"Error deleting model {model_file}: {e}", file=sys.stderr)
                    
            # Cari dan hapus file checkpoint jika ada
            checkpoint_pattern = os.path.join(model_dir, f"*{split_percentage}*")
            checkpoint_files = glob.glob(checkpoint_pattern)
            
            for checkpoint_file in checkpoint_files:
                try:
                    if os.path.isfile(checkpoint_file):
                        os.remove(checkpoint_file)
                        print(f"Deleted old checkpoint: {checkpoint_file}", file=sys.stderr)
                except Exception as e:
                    print(f"Error deleting checkpoint {checkpoint_file}: {e}", file=sys.stderr)
    except Exception as e:
        print(f"Error in delete_old_models: {e}", file=sys.stderr)


def train_model(split_percentage):
    """
    Fungsi utama untuk training model Xception.
    
    Args:
        split_percentage: Rasio split data untuk training
    
    Returns:
        Dictionary berisi hasil training dan evaluasi
    """
    try:
        # Hapus model lama sebelum memulai training
        delete_old_models(split_percentage)
        
        # Load data dari database
        print(f"Loading data for split ratio: {split_percentage}...", file=sys.stderr)
        X_train, X_test, y_train, y_test, test_filenames, test_types = load_data(
            split_percentage
        )

        # Validasi bahwa data berhasil dimuat
        if X_train is None:
            return {
                "error": f"Failed to load data from database for split ratio {split_percentage}"
            }

        # Bangun model Xception
        print("Building model...", file=sys.stderr)
        model = build_model()

        # Training model
        print("Training model...", file=sys.stderr)

        history = model.fit(
            X_train,
            y_train,
            epochs=EPOCHS,  # Jumlah epoch
            batch_size=BATCH_SIZE,  # Ukuran batch
            validation_data=(X_test, y_test),  # Data validasi
            verbose=0,  # Non-verbose output
        )

        # Lakukan prediksi pada data testing
        print("Making predictions...", file=sys.stderr)
        y_pred_proba = model.predict(X_test, verbose=0)  # Probabilitas prediksi
        y_pred = (y_pred_proba > 0.5).astype("int32").flatten()  # Konversi ke binary prediction

        # Hitung berbagai metrik evaluasi
        accuracy = accuracy_score(y_test, y_pred)  # Akurasi
        precision = precision_score(y_test, y_pred, zero_division=0)  # Precision
        recall = recall_score(y_test, y_pred, zero_division=0)  # Recall
        f1 = f1_score(y_test, y_pred, zero_division=0)  # F1-score
        cm = confusion_matrix(y_test, y_pred)  # Confusion matrix
        
        # Calculate AUC-ROC curve
        fpr, tpr, _ = roc_curve(y_test, y_pred_proba)  # ROC curve
        auc_roc = auc(fpr, tpr)  # AUC score

        # Siapkan detail predictions untuk setiap gambar test
        predictions = []
        for i in range(len(test_filenames)):
            # Konversi prediksi numerik ke label
            pred_label = "real" if y_pred[i] == 0 else "fake"
            # Confidence score
            confidence = float(y_pred_proba[i][0])

            # Tambahkan ke list predictions
            predictions.append(
                {
                    "filename": test_filenames[i],
                    "prediction": pred_label,
                    "confidence": confidence,
                }
            )

        # Simpan model yang telah ditraining
        model_dir = os.path.join(os.path.dirname(__file__), "model")
        os.makedirs(model_dir, exist_ok=True)  # Buat direktori jika belum ada
        model_path = os.path.join(model_dir, f"xception_model_{split_percentage}.h5")
        save_model(model, model_path)  # Simpan model
        print(f"Model saved to {model_path}", file=sys.stderr)

        # Siapkan hasil training dalam format dictionary
        results = {
            "accuracy": float(accuracy),
            "precision": float(precision),
            "recall": float(recall),
            "f1_score": float(f1),
            "auc_roc": float(auc_roc),
            "confusion_matrix": cm.tolist(),  # Konversi numpy array ke list
            "split_ratio": split_percentage,
            "predictions": predictions,  # Detail predictions per gambar
            "preprocessing_info": {  # Informasi tentang preprocessing
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
        # Jika terjadi error selama training
        error_msg = f"Error in train_model: {str(e)}\n{traceback.format_exc()}"
        print(error_msg, file=sys.stderr)
        return {"error": error_msg}


if __name__ == "__main__":
    """
    Entry point utama ketika script dijalankan langsung.
    """
    try:
        # Parse argument command line (split ratio)
        if len(sys.argv) < 2:
            split_percentage = 80  # Default value jika tidak ada argument
        else:
            split_percentage = int(sys.argv[1])  # Ambil dari command line

        # Mulai training
        print(f"Starting training with split: {split_percentage}%", file=sys.stderr)
        results = train_model(split_percentage)

        # Output hasil dalam format JSON
        print(json.dumps(results))

    except Exception as e:
        # Handle error di main function
        error_msg = f"Error in main: {str(e)}\n{traceback.format_exc()}"
        print(error_msg, file=sys.stderr)
        print(json.dumps({"error": error_msg}))