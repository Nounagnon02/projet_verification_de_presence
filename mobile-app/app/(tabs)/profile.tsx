import { View, Text, ScrollView, Pressable } from 'react-native';
import { Card } from '../../src/components/ui/Card';
import { Button } from '../../src/components/ui/Button';
import { useAuth } from '../../src/auth/AuthContext';
import { showToast } from '../../src/utils/toast-config';
import { router } from 'expo-router';
import { User, Mail, Hash, GraduationCap, LogOut } from 'lucide-react-native';

export default function ProfileTab() {
  const { user, logout } = useAuth();

  async function handleLogout() {
    try {
      await logout();
      router.replace('/login');
    } catch {
      showToast('error', 'Erreur', 'Impossible de se déconnecter.');
    }
  }

  return (
    <ScrollView className="flex-1 bg-background px-4 pt-4">
      {/* Avatar + Nom */}
      <Card className="mb-4 items-center">
        <View className="mb-3 h-20 w-20 items-center justify-center rounded-full bg-primary">
          <User size={40} color="#ffffff" />
        </View>
        <Text className="font-headline text-xl text-on-surface">
          {user?.prenom ?? ''} {user?.nom ?? user?.name ?? 'Étudiant'}
        </Text>
        <Text className="mt-1 text-sm text-on-surface-variant">
          {user?.role === 'etudiant' ? 'Étudiant' : user?.role ?? ''}
        </Text>
      </Card>

      {/* Détails */}
      <Card className="mb-4">
        <View className="gap-y-3">
          <View className="flex-row items-center gap-x-3">
            <Mail size={18} color="#757680" />
            <Text className="text-base text-on-surface">{user?.email ?? '—'}</Text>
          </View>
          {user?.identifiant_unique && (
            <View className="flex-row items-center gap-x-3">
              <Hash size={18} color="#757680" />
              <Text className="text-base text-on-surface">
                {user.identifiant_unique}
              </Text>
            </View>
          )}
          {user?.matricule && (
            <View className="flex-row items-center gap-x-3">
              <GraduationCap size={18} color="#757680" />
              <Text className="text-base text-on-surface">{user.matricule}</Text>
            </View>
          )}
        </View>
      </Card>

      {/* Déconnexion */}
      <Button variant="danger" size="lg" onPress={handleLogout}>
        Déconnexion
      </Button>

      <Text className="mt-6 mb-8 text-center text-xs text-on-surface-variant">
        Présence UAC v1.0.0{'\n'}
        UAC — Université d'Abomey-Calavi
      </Text>
    </ScrollView>
  );
}