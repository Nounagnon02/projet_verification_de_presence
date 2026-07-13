import { View, Text, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { router } from 'expo-router';
import { useState } from 'react';
import { useAuth } from '../src/auth/AuthContext';
import { Button } from '../src/components/ui/Button';
import { Input } from '../src/components/ui/Input';
import { showToast } from '../src/utils/toast-config';
import { Mail, Lock, LogIn } from 'lucide-react-native';

export default function LoginScreen() {
  const { login, isAuthenticated } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({});

  if (isAuthenticated) {
    router.replace('/(tabs)');
    return null;
  }

  async function handleLogin() {
    const newErrors: { email?: string; password?: string } = {};
    if (!email.trim()) newErrors.email = "L'email est requis.";
    if (!password) newErrors.password = 'Le mot de passe est requis.';
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    setErrors({});

    setLoading(true);
    try {
      await login(email.trim(), password);
      router.replace('/(tabs)');
    } catch (err: unknown) {
      const message =
        err instanceof Error ? err.message : 'Identifiants invalides.';
      showToast('error', 'Échec de connexion', message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      className="flex-1 bg-background"
    >
      <ScrollView
        contentContainerClassName="flex-1 justify-center px-6"
        keyboardShouldPersistTaps="handled"
      >
        {/* Logo / Titre */}
        <View className="mb-10 items-center">
          <View className="mb-4 h-16 w-16 items-center justify-center rounded-xxl bg-primary">
            <LogIn size={32} color="#ffffff" />
          </View>
          <Text className="font-headline text-2xl text-primary">Présence UAC</Text>
          <Text className="mt-1 text-base text-on-surface-variant">
            Validation de présence étudiante
          </Text>
        </View>

        {/* Formulaire */}
        <View className="gap-y-4">
          <Input
            label="Email"
            placeholder="votre.email@uac.bj"
            value={email}
            onChangeText={(t) => {
              setEmail(t);
              if (errors.email) setErrors((e) => ({ ...e, email: undefined }));
            }}
            error={errors.email}
            keyboardType="email-address"
            autoCapitalize="none"
            autoComplete="email"
            leftIcon={<Mail size={20} color="#757680" />}
          />

          <Input
            label="Mot de passe"
            placeholder="••••••••"
            value={password}
            onChangeText={(t) => {
              setPassword(t);
              if (errors.password) setErrors((e) => ({ ...e, password: undefined }));
            }}
            error={errors.password}
            secureTextEntry
            autoComplete="password"
            leftIcon={<Lock size={20} color="#757680" />}
          />

          <Button
            variant="primary"
            size="lg"
            loading={loading}
            onPress={handleLogin}
            className="mt-2"
          >
            Se connecter
          </Button>
        </View>

        <Text className="mt-8 text-center text-xs text-on-surface-variant">
          UAC — Université d'Abomey-Calavi{'\n'}
          Service de validation de présence
        </Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}