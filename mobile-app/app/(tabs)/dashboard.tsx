import { View, Text, ScrollView } from 'react-native';
import { Card } from '../../src/components/ui/Card';
import { LoadingSpinner } from '../../src/components/ui/LoadingSpinner';
import { useAuth } from '../../src/auth/AuthContext';
import { useQuery } from '@tanstack/react-query';
import apiClient from '../../src/api/client';

interface StudentStats {
  total: number;
  validees: number;
  rejetees: number;
  en_attente: number;
  taux_validation: number;
}

export default function DashboardTab() {
  const { user } = useAuth();

  const { data: stats, isLoading } = useQuery({
    queryKey: ['my-stats'],
    queryFn: async () => {
      const { data: body } = await apiClient.get('/presence/my-stats');
      return (body as { success: boolean; data: StudentStats }).data;
    },
    staleTime: 2 * 60 * 1000, // 2 minutes
  });

  return (
    <ScrollView className="flex-1 bg-background px-4 pt-4">
      {/* Bienvenue */}
      <Card className="mb-4">
        <Text className="font-headline text-xl text-primary">
          Bonjour, {user?.prenom ?? user?.name ?? 'Étudiant'} 👋
        </Text>
        <Text className="mt-2 text-base text-on-surface-variant">
          Validez votre présence en scannant le QR code affiché par votre
          enseignant.
        </Text>
      </Card>

      {/* Statistiques */}
      <Card title="Vos statistiques" subtitle="Ce semestre">
        {isLoading ? (
          <LoadingSpinner size="small" />
        ) : (
          <View className="flex-row justify-between">
            <View className="items-center">
              <Text className="font-headline text-2xl text-primary">
                {stats?.total ?? '--'}
              </Text>
              <Text className="mt-1 text-sm text-on-surface-variant">
                Scans
              </Text>
            </View>
            <View className="items-center">
              <Text className="font-headline text-2xl text-secondary">
                {stats?.validees ?? '--'}
              </Text>
              <Text className="mt-1 text-sm text-on-surface-variant">
                Validés
              </Text>
            </View>
            <View className="items-center">
              <Text className="font-headline text-2xl text-error">
                {stats?.rejetees ?? '--'}
              </Text>
              <Text className="mt-1 text-sm text-on-surface-variant">
                Rejetés
              </Text>
            </View>
          </View>
        )}
        {/* Taux de validation */}
        {stats && (
          <View className="mt-4 items-center">
            <View className="h-2 w-full overflow-hidden rounded-full bg-surface-container">
              <View
                className="h-full rounded-full bg-secondary"
                style={{ width: `${Math.min(stats.taux_validation, 100)}%` }}
              />
            </View>
            <Text className="mt-1 text-sm text-on-surface-variant">
              {stats.taux_validation}% de validation
            </Text>
          </View>
        )}
      </Card>
    </ScrollView>
  );
}