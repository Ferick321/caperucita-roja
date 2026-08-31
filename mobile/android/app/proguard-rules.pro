# Flutter y sus complementos
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
-dontwarn io.flutter.embedding.**

# Almacenamiento seguro
-keep class com.it_nomads.fluttersecurestorage.** { *; }

# No se conservan los registros de depuracion en la version publicada
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
}
