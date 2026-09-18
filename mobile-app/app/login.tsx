import { View, Text, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { router } from 'expo-router';
import { useState } from 'react';
import { useAuth, ErreurConnexion } from '../src/auth/AuthContext';
import { Button } from '../src/components/ui/Button';
import { Input } from '../src/components/ui/Input';
import { showToast } from '../src/utils/toast-config';
import { Mail, Key, LogIn, Lock } from 'lucide-react-native';

/** Longueur exacte du code d'accès tiré et envoyé par l'administration. */
const LONGUEUR_CODE = 6;

interface ErreursFormulaire {
  email?: string;
  identifiantUnique?: string;
  code?: string;
}

export default function LoginScreen() {
  const { login, isAuthenticated } = useAuth();
  const [email, setEmail] = useState('');
  const [identifiantUnique, setIdentifiantUnique] = useState('');
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<ErreursFormulaire>({});
  // Message d'aide affiché quand le serveur répond « code_absent » : l'étudiant
  // ne peut rien corriger lui-même, il doit réclamer son code.
  const [codeAbsent, setCodeAbsent] = useState(false);

  if (isAuthenticated) {
    router.replace('/(tabs)');
    return null;
  }

  async function handleLogin() {
    const newErrors: ErreursFormulaire = {};
    if (!email.trim()) newErrors.email = "L'email est requis.";
    if (!identifiantUnique.trim()) newErrors.identifiantUnique = "L'identifiant unique est requis.";
    // Contrôle local : le serveur répond 422 avec un message volontairement
    // générique, qui ne dirait pas à l'étudiant que son code est incomplet.
    if (code.trim().length !== LONGUEUR_CODE) {
      newErrors.code = `Le code d'accès comporte ${LONGUEUR_CODE} chiffres.`;
    }
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    setErrors({});
    setCodeAbsent(false);

    setLoading(true);
    try {
      await login(email.trim(), identifiantUnique, code.trim());
      router.replace('/(tabs)');
    } catch (err: unknown) {
      const message =
        err instanceof Error ? err.message : 'Identifiants invalides.';

      if (err instanceof ErreurConnexion && err.codeMetier === 'code_absent') {
        setCodeAbsent(true);
        showToast('error', "Code d'accès manquant", message);
        return;
      }

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
          <Text className="font-headline text-2xl text-primary">UAC Présences</Text>
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
            label="Identifiant unique"
            placeholder="NOM_PRENOM_MATRICULE_FILIERE_ANNEE"
            value={identifiantUnique}
            onChangeText={(t) => {
              setIdentifiantUnique(t);
              if (errors.identifiantUnique) setErrors((e) => ({ ...e, identifiantUnique: undefined }));
            }}
            error={errors.identifiantUnique}
            autoCapitalize="characters"
            leftIcon={<Key size={20} color="#757680" />}
          />

          {/* Masqué et numérique : c'est un secret à 6 chiffres, pas un
              identifiant. Le clavier alphabétique par défaut obligeait à
              basculer de page à chaque connexion. */}
          <Input
            label="Code d'accès"
            placeholder="••••••"
            value={code}
            onChangeText={(t) => {
              // Les chiffres seuls : un espace ou un tiret collé au collage
              // faisait échouer la connexion sans que rien ne soit visible.
              const chiffres = t.replace(/\D/g, '').slice(0, LONGUEUR_CODE);
              setCode(chiffres);
              if (errors.code) setErrors((e) => ({ ...e, code: undefined }));
            }}
            error={errors.code}
            hint={`Code à ${LONGUEUR_CODE} chiffres reçu par e-mail`}
            keyboardType="number-pad"
            secureTextEntry
            maxLength={LONGUEUR_CODE}
            autoComplete="one-time-code"
            textContentType="oneTimeCode"
            leftIcon={<Lock size={20} color="#757680" />}
          />

          {codeAbsent && (
            <View className="rounded-lg border border-error bg-error-container px-3 py-2.5">
              <Text className="text-sm text-on-error-container">
                Aucun code d&apos;accès n&apos;a encore été envoyé pour ce compte.
                Demandez-le à votre administration : il vous parviendra par
                e-mail avec vos identifiants.
              </Text>
            </View>
          )}

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
          UAC — Université d&apos;Abomey-Calavi{'\n'}
          Service de validation de présence
        </Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
