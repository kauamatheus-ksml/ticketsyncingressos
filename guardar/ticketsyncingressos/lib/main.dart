import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'ingresso.dart';

void main() => runApp(MyApp());

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Ingressos Comprados',
      theme: ThemeData(
        primarySwatch: Colors.blue,
      ),
      home: IngressosScreen(),
    );
  }
}

class IngressosScreen extends StatefulWidget {
  @override
  _IngressosScreenState createState() => _IngressosScreenState();
}

class _IngressosScreenState extends State<IngressosScreen> {
  late Future<List<Ingresso>> futureIngressos;

  Future<List<Ingresso>> fetchIngressos() async {
    final response = await http.get(Uri.parse("https://ticketsync.com.br/list_ingressos.php"));
    if (response.statusCode == 200) { 
      List jsonResponse = json.decode(response.body);
      return jsonResponse.map((data) => Ingresso.fromJson(data)).toList();
    } else {
      throw Exception('Falha ao carregar ingressos');
    }
  }

  @override
  void initState() {
    super.initState();
    futureIngressos = fetchIngressos();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Ingressos Comprados'),
      ),
      body: FutureBuilder<List<Ingresso>>(
        future: futureIngressos,
        builder: (context, snapshot) {
          if (snapshot.hasData) {
            List<Ingresso> ingressos = snapshot.data!;
            return ListView.builder(
              itemCount: ingressos.length,
              itemBuilder: (context, index) {
                final ingresso = ingressos[index];
                return ListTile(
                  title: Text("${ingresso.nome} ${ingresso.sobrenome}"),
                  subtitle: Text("Order: ${ingresso.orderId} - R\$ ${ingresso.valorTotal.toStringAsFixed(2)}"),
                  trailing: Text(ingresso.status),
                  onTap: () {
                    // Aqui você pode implementar a navegação para uma tela de detalhes,
                    // por exemplo, exibindo mais informações ou permitindo baixar o PDF.
                  },
                );
              },
            );
          } else if (snapshot.hasError) {
            return Center(child: Text("${snapshot.error}"));
          }
          return Center(child: CircularProgressIndicator());
        },
      ),
    );
  }
}
