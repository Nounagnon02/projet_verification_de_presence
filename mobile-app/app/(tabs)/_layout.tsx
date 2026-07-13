import { Tabs } from 'expo-router';
import { QrCode, LayoutDashboard, Clock, User } from 'lucide-react-native';

export default function TabLayout() {
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