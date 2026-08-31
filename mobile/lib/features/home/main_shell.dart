import 'package:flutter/material.dart';

import '../../core/services/auth_service.dart';
import '../appointments/appointments_screen.dart';
import '../booking/booking_screen.dart';
import '../profile/profile_screen.dart';
import 'home_screen.dart';

/// Contenedor principal con la barra de navegacion inferior.
class MainShell extends StatefulWidget {
  const MainShell({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<MainShell> createState() => MainShellState();
}

class MainShellState extends State<MainShell> {
  late int _index = widget.initialIndex;

  /// Permite que otras pantallas salten a una pestana concreta.
  void goTo(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    final List<Widget> screens = <Widget>[
      const HomeScreen(),
      const BookingScreen(),
      const AppointmentsScreen(),
      const ProfileScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: screens),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: (int index) async {
          // "Mis citas" y "Perfil" exigen sesion; "Reservar" la pide al final.
          const List<int> requiresSession = <int>[2, 3];

          if (requiresSession.contains(index) && !AuthService.instance.isLoggedIn) {
            await Navigator.of(context).pushNamed('/login');

            if (!AuthService.instance.isLoggedIn || !mounted) {
              return;
            }
          }

          setState(() => _index = index);
        },
        items: const <BottomNavigationBarItem>[
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home_rounded),
            label: 'Inicio',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.add_circle_outline),
            activeIcon: Icon(Icons.add_circle_rounded),
            label: 'Reservar',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.event_note_outlined),
            activeIcon: Icon(Icons.event_note_rounded),
            label: 'Mis citas',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person_rounded),
            label: 'Perfil',
          ),
        ],
      ),
    );
  }
}
