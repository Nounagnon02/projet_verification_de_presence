import { Tabs } from 'expo-router';
import { QrCode, LayoutDashboard, Clock, User, MonitorSmartphone } from 'lucide-react-native';
import { useAuth } from '../../src/auth/AuthContext';

export default function TabLayout() {
  const { user } = useAuth();

  // L'onglet du délégué est masqué pour les autres étudiants, plutôt que
  // présent et affichant un refus.
  const estResponsable = Boolean(user?.est_responsable);

  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: '#011549' },
        headerTintColor: '#ffffff',
        headerTitleStyle: { fontFamily: 'Sora', fontWeight: '600' },
        tabBarActiveTintColor: '#011549',
        tabBarInactiveTintColor: '#757680',
        tabBarStyle: {
          backgroundColor: '#ffffff',
          borderTopColor: '#c5c6d1',
        },
        tabBarLabelStyle: {
          fontFamily: 'Inter',
          fontSize: 12,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Scanner',
          headerTitle: 'Scanner QR',
          tabBarIcon: ({ color, size }) => <QrCode color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="dashboard"
        options={{
          title: 'Accueil',
          headerTitle: 'Tableau de bord',
          tabBarIcon: ({ color, size }) => <LayoutDashboard color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="qrcode"
        options={{
          title: 'QR du cours',
          headerTitle: 'QR Code du cours',
          href: estResponsable ? undefined : null,
          tabBarIcon: ({ color, size }) => <MonitorSmartphone color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="history"
        options={{
          title: 'Historique',
          headerTitle: 'Mes scans',
          tabBarIcon: ({ color, size }) => <Clock color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profil',
          headerTitle: 'Mon profil',
          tabBarIcon: ({ color, size }) => <User color={color} size={size} />,
        }}
      />
    </Tabs>
  );
}