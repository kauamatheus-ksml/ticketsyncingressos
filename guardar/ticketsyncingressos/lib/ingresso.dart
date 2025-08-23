class Ingresso {
  final String orderId;
  final String nome;
  final String sobrenome;
  final String email;
  final double valorTotal;
  final String createdAt;
  final String ticketCode;
  final String status;

  Ingresso({
    required this.orderId,
    required this.nome,
    required this.sobrenome,
    required this.email,
    required this.valorTotal,
    required this.createdAt,
    required this.ticketCode,
    required this.status,
  });

  factory Ingresso.fromJson(Map<String, dynamic> json) {
    return Ingresso(
      orderId: json['order_id'],
      nome: json['nome'],
      sobrenome: json['sobrenome'],
      email: json['email'],
      valorTotal: double.parse(json['valor_total'].toString()),
      createdAt: json['created_at'],
      ticketCode: json['ticket_code'],
      status: json['status'],
    );
  }
}
